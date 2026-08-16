<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Vvdboogaard\ErrorPages\Data\ErrorContext;
use Vvdboogaard\ErrorPages\Data\ExceptionDetails;
use Vvdboogaard\ErrorPages\Enums\DetailVisibility;
use Vvdboogaard\ErrorPages\Enums\Theme;
use Vvdboogaard\ErrorPages\Integrations\Filament;
use Vvdboogaard\ErrorPages\Support\ActionFactory;
use Vvdboogaard\ErrorPages\Support\Icons;
use Vvdboogaard\ErrorPages\Support\MessageNumber;
use Vvdboogaard\ErrorPages\Support\Palette;
use Vvdboogaard\ErrorPages\Support\RequestId;
use Vvdboogaard\ErrorPages\Support\RetryAfter;

/**
 * Resolves a Throwable into the fully-populated ErrorContext the presentations
 * render from.
 */
class ErrorContextFactory
{
    public function __construct(
        protected readonly Application $app,
        protected readonly Config $config,
        protected readonly Translator $translator,
        protected readonly MessageNumber $messageNumber,
        protected readonly RequestId $requestId,
        protected readonly RetryAfter $retryAfter,
    ) {}

    public function make(Request $request, ?Throwable $exception, ?int $statusCode = null): ErrorContext
    {
        $statusCode ??= $this->statusCode($exception);
        $settings = $this->settings();

        $retryAt = $this->retryAfter->resolve($exception, $request);
        $messageNumber = $this->messageNumber->for($exception, $statusCode);
        $requestId = $this->requestId->resolve($request);

        $brand = $this->brand($request);
        $supportEmail = $this->supportEmail($statusCode);

        $context = new ErrorContext(
            statusCode: $statusCode,
            title: $this->line($statusCode, 'title', $messageNumber),
            message: $this->message($statusCode, $exception, $messageNumber),
            reason: $this->optionalLine($statusCode, 'reason', $messageNumber),
            explanation: $this->optionalLine($statusCode, 'explanation', $messageNumber),
            suggestions: $this->suggestions($statusCode),
            icon: Icons::forStatus($statusCode),
            messageNumber: $messageNumber,
            requestId: $requestId,
            retryAt: $retryAt,
            details: $this->details($exception, $statusCode),
            actions: [],
            brand: $brand,
            supportEmail: $supportEmail,
            palette: $this->palette($request),
            theme: Theme::parse($settings['theme'] ?? null),
            occurredAt: CarbonImmutable::now(),
            locale: $this->locale(),
            exception: $exception,
            request: $request,
        );

        // Actions need the finished context (for the pre-filled support mailto),
        // so they are attached in a second pass.
        return $this->withActions($context, $request);
    }

    public function statusCode(?Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return $status >= 400 && $status <= 599 ? $status : 500;
        }

        return 500;
    }

    public function shouldShowDetails(int $statusCode): bool
    {
        /** @var list<int>|null $codes */
        $codes = $this->setting('details.codes');

        if (is_array($codes) && ! in_array($statusCode, $codes, true)) {
            return false;
        }

        return match (DetailVisibility::parse($this->setting('details.visibility'))) {
            DetailVisibility::Always => true,
            DetailVisibility::Never => false,
            DetailVisibility::Auto => $this->debugEnvironment(),
        };
    }

    protected function withActions(ErrorContext $context, Request $request): ErrorContext
    {
        $settings = $this->settings();
        $urls = ActionFactory::resolveUrls($settings, $request);

        $actions = (new ActionFactory($settings, $this->translator))->for(
            $context->statusCode,
            [
                ...$urls,
                'support_mailto' => $context->supportMailto($this->stringSetting('links.support_subject')),
                'has_retry' => $context->hasRetry(),
            ],
            $request,
        );

        return new ErrorContext(
            statusCode: $context->statusCode,
            title: $context->title,
            message: $context->message,
            reason: $context->reason,
            explanation: $context->explanation,
            suggestions: $context->suggestions,
            icon: $context->icon,
            messageNumber: $context->messageNumber,
            requestId: $context->requestId,
            retryAt: $context->retryAt,
            details: $context->details,
            actions: $actions,
            brand: $context->brand,
            supportEmail: $context->supportEmail,
            palette: $context->palette,
            theme: $context->theme,
            occurredAt: $context->occurredAt,
            locale: $context->locale,
            exception: $context->exception,
            request: $context->request,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Copy
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a translation line, walking status → family (4xx/5xx) → default.
     */
    protected function optionalLine(int $statusCode, string $key, ?string $messageNumber = null): ?string
    {
        $replace = $this->replacements($statusCode, $messageNumber);

        foreach ($this->translationKeys($statusCode, $key) as $candidate) {
            if (! $this->translator->has($candidate, $this->locale())) {
                continue;
            }

            $line = $this->translator->get($candidate, $replace, $this->locale());

            if (is_string($line) && trim($line) !== '' && $line !== $candidate) {
                return $line;
            }
        }

        return null;
    }

    protected function line(int $statusCode, string $key, ?string $messageNumber = null): string
    {
        return $this->optionalLine($statusCode, $key, $messageNumber) ?? (string) $statusCode;
    }

    /**
     * @return list<string>
     */
    protected function translationKeys(int $statusCode, string $key): array
    {
        $family = intdiv($statusCode, 100).'xx';

        return [
            "error-pages::errors.{$statusCode}.{$key}",
            "error-pages::errors.{$family}.{$key}",
            "error-pages::errors.default.{$key}",
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(int $statusCode, ?string $messageNumber): array
    {
        return [
            'status' => (string) $statusCode,
            'brand' => (string) ($this->brandName() ?? ''),
            'message_number' => $messageNumber ?? '',
            'support_email' => (string) ($this->supportEmail($statusCode) ?? ''),
        ];
    }

    /**
     * The main sentence shown under the title.
     *
     * A message passed to `abort(403, 'You may not edit published posts.')` is
     * far more useful than a generic line — but only for the status codes where
     * such a message is deliberate. Framework-generated messages (a
     * ModelNotFoundException naming your model class, any 5xx) stay hidden.
     */
    protected function message(int $statusCode, ?Throwable $exception, ?string $messageNumber): string
    {
        $fallback = $this->line($statusCode, 'message', $messageNumber);

        if (! $exception instanceof HttpExceptionInterface) {
            return $fallback;
        }

        if ($this->setting('messages.use_exception_message') !== true) {
            return $fallback;
        }

        /** @var list<int> $allowed */
        $allowed = $this->setting('messages.use_exception_message_codes') ?? [];

        if (! in_array($statusCode, $allowed, true)) {
            return $fallback;
        }

        $message = trim($exception->getMessage());

        if ($message === '') {
            return $fallback;
        }

        $maxLength = (int) ($this->setting('messages.max_exception_message_length') ?? 300);

        return mb_strlen($message) > $maxLength
            ? $fallback
            : $message;
    }

    /**
     * @return list<string>
     */
    protected function suggestions(int $statusCode): array
    {
        foreach ($this->translationKeys($statusCode, 'suggestions') as $candidate) {
            if (! $this->translator->has($candidate, $this->locale())) {
                continue;
            }

            $lines = $this->translator->get($candidate, $this->replacements($statusCode, null), $this->locale());

            if (is_array($lines)) {
                return array_values(array_filter(
                    array_map(static fn (mixed $line): string => is_string($line) ? $line : '', $lines),
                    static fn (string $line): bool => trim($line) !== '',
                ));
            }
        }

        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    protected function brand(?Request $request = null): array
    {
        return [
            'name' => $this->brandName($request),
            'logo' => $this->brandLogo($request),
            'logo_dark' => $this->stringSetting('brand.logo_dark'),
            'logo_height' => (int) ($this->setting('brand.logo_height') ?? 32),
            'show_name_beside_logo' => (bool) ($this->setting('brand.show_name_beside_logo') ?? false),
        ];
    }

    protected function brandName(?Request $request = null): ?string
    {
        $configured = $this->stringSetting('brand.name');

        if ($configured !== null) {
            return $configured;
        }

        if ($this->filamentInherits('brand_name', $request)) {
            $name = Filament::brandName($request);

            if ($name !== null) {
                return $name;
            }
        }

        $appName = $this->config->get('app.name');

        return is_string($appName) && $appName !== '' ? $appName : null;
    }

    protected function brandLogo(?Request $request = null): ?string
    {
        $configured = $this->stringSetting('brand.logo');

        if ($configured !== null) {
            return $configured;
        }

        return $this->filamentInherits('brand_logo', $request)
            ? Filament::brandLogo($request)
            : null;
    }

    protected function palette(?Request $request = null): Palette
    {
        /** @var array{primary?: string|null, light?: string|null, dark?: string|null, auto_contrast?: bool} $colors */
        $colors = $this->setting('colors') ?? [];

        if ($this->filamentInherits('primary_color', $request)) {
            $shade = (int) ($this->setting('filament.color_shade') ?? 600);
            $filamentColor = Filament::primaryColor($request, $shade);

            if ($filamentColor !== null) {
                // The panel's colour is the source of truth; explicit light/dark
                // overrides in this package's config still win.
                $colors['primary'] = $filamentColor;
            }
        }

        return Palette::fromConfig($colors, Theme::parse($this->setting('theme')));
    }

    protected function supportEmail(int $statusCode): ?string
    {
        $email = $this->stringSetting('links.support_email');

        if ($email === null) {
            return null;
        }

        /** @var list<int>|null $codes */
        $codes = $this->setting('links.support_email_codes');

        if ($codes === null) {
            return $email;
        }

        return in_array($statusCode, $codes, true) ? $email : null;
    }

    protected function locale(): string
    {
        $configured = $this->stringSetting('locale');

        return $configured ?? $this->app->getLocale();
    }

    /*
    |--------------------------------------------------------------------------
    | Exception details
    |--------------------------------------------------------------------------
    */

    protected function details(?Throwable $exception, int $statusCode): ?ExceptionDetails
    {
        if ($exception === null || ! $this->shouldShowDetails($statusCode)) {
            return null;
        }

        return ExceptionDetails::fromThrowable(
            $exception,
            $this->app->basePath(),
            (int) ($this->setting('details.stack_frames') ?? 12),
        );
    }

    protected function debugEnvironment(): bool
    {
        if ($this->config->get('app.debug') === true) {
            return true;
        }

        /** @var list<string> $environments */
        $environments = $this->setting('details.environments') ?? [];

        return in_array($this->app->environment(), $environments, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Config helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    protected function settings(): array
    {
        /** @var array<string, mixed> $settings */
        $settings = $this->config->get('error-pages', []);

        return $settings;
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->config->get('error-pages.'.$key, $default);
    }

    protected function stringSetting(string $key): ?string
    {
        $value = $this->setting($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    protected function filamentInherits(string $feature, ?Request $request): bool
    {
        if (! Filament::installed() || $this->setting('filament.enabled') !== true) {
            return false;
        }

        if ($this->setting('filament.inherit.'.$feature) !== true) {
            return false;
        }

        if ($this->setting('filament.only_on_panel_routes') === true) {
            return $request !== null && Filament::isPanelRequest($request);
        }

        return true;
    }
}
