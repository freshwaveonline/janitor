<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor;

use Carbon\CarbonImmutable;
use FreshwaveOnline\Janitor\Contracts\ActionResolver;
use FreshwaveOnline\Janitor\Contracts\BrandingResolver;
use FreshwaveOnline\Janitor\Contracts\ErrorContextBuilder;
use FreshwaveOnline\Janitor\Contracts\MessageNumberGenerator;
use FreshwaveOnline\Janitor\Contracts\RequestIdResolver;
use FreshwaveOnline\Janitor\Contracts\RetryAfterResolver;
use FreshwaveOnline\Janitor\Data\Branding;
use FreshwaveOnline\Janitor\Data\ErrorContext;
use FreshwaveOnline\Janitor\Data\ExceptionDetails;
use FreshwaveOnline\Janitor\Enums\DetailVisibility;
use FreshwaveOnline\Janitor\Enums\Theme;
use FreshwaveOnline\Janitor\Support\Guard;
use FreshwaveOnline\Janitor\Support\Icons;
use FreshwaveOnline\Janitor\Support\Palette;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Resolves a Throwable into the fully-populated ErrorContext the presentations
 * render from.
 *
 * Every collaborator is injected through a contract, and every method here is
 * protected, so you can either swap one piece (bind a different
 * MessageNumberGenerator) or subclass this whole factory to change where the
 * copy comes from.
 */
class ErrorContextFactory implements ErrorContextBuilder
{
    public function __construct(
        protected readonly Application $app,
        protected readonly Config $config,
        protected readonly Translator $translator,
        protected readonly MessageNumberGenerator $messageNumbers,
        protected readonly RequestIdResolver $requestIds,
        protected readonly RetryAfterResolver $retryAfter,
        protected readonly BrandingResolver $branding,
        protected readonly ActionResolver $actions,
    ) {}

    public function make(Request $request, ?Throwable $exception, ?int $statusCode = null): ErrorContext
    {
        $statusCode ??= $this->statusCode($exception);

        $branding = $this->branding->resolve($request, $statusCode);
        $messageNumber = $this->messageNumbers->for($exception, $statusCode);

        $context = new ErrorContext(
            statusCode: $statusCode,
            title: $this->line($statusCode, 'title', $messageNumber, $branding),
            message: $this->message($statusCode, $exception, $messageNumber, $branding),
            reason: $this->optionalLine($statusCode, 'reason', $messageNumber, $branding),
            explanation: $this->optionalLine($statusCode, 'explanation', $messageNumber, $branding),
            suggestions: $this->suggestions($statusCode, $branding),
            icon: Icons::forStatus($statusCode),
            messageNumber: $messageNumber,
            requestId: $this->requestIds->resolve($request),
            retryAt: $this->retryAfter->resolve($exception, $request),
            details: $this->details($exception, $statusCode),
            actions: [],
            branding: $branding,
            palette: Palette::fromConfig($branding->colors(), $this->theme()),
            theme: $this->theme(),
            occurredAt: CarbonImmutable::now(),
            locale: $this->locale(),
            exception: $exception,
            request: $request,
        );

        // Actions need the finished context (for the pre-filled support mailto
        // and the retry state), so they are attached in a second pass.
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
            actions: $this->actions->for($context, $request),
            branding: $context->branding,
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
    protected function optionalLine(int $statusCode, string $key, ?string $messageNumber, Branding $branding): ?string
    {
        $replace = $this->replacements($statusCode, $messageNumber, $branding);

        return Guard::value(function () use ($statusCode, $key, $replace): ?string {
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
        });
    }

    protected function line(int $statusCode, string $key, ?string $messageNumber, Branding $branding): string
    {
        return $this->optionalLine($statusCode, $key, $messageNumber, $branding) ?? (string) $statusCode;
    }

    /**
     * @return list<string>
     */
    protected function translationKeys(int $statusCode, string $key): array
    {
        $family = intdiv($statusCode, 100).'xx';

        return [
            "janitor::errors.{$statusCode}.{$key}",
            "janitor::errors.{$family}.{$key}",
            "janitor::errors.default.{$key}",
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(int $statusCode, ?string $messageNumber, Branding $branding): array
    {
        return [
            'status' => (string) $statusCode,
            'brand' => $branding->name ?? '',
            'message_number' => $messageNumber ?? '',
            'support_email' => $branding->supportEmail ?? '',
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
    protected function message(int $statusCode, ?Throwable $exception, ?string $messageNumber, Branding $branding): string
    {
        $fallback = $this->line($statusCode, 'message', $messageNumber, $branding);

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

        $maxLength = $this->intSetting('messages.max_exception_message_length', 300);

        return mb_strlen($message) > $maxLength ? $fallback : $message;
    }

    /**
     * @return list<string>
     */
    protected function suggestions(int $statusCode, Branding $branding): array
    {
        return Guard::value(function () use ($statusCode, $branding): array {
            foreach ($this->translationKeys($statusCode, 'suggestions') as $candidate) {
                if (! $this->translator->has($candidate, $this->locale())) {
                    continue;
                }

                $lines = $this->translator->get($candidate, $this->replacements($statusCode, null, $branding), $this->locale());

                if (is_array($lines)) {
                    return array_values(array_filter(
                        array_map(static fn (mixed $line): string => is_string($line) ? $line : '', $lines),
                        static fn (string $line): bool => trim($line) !== '',
                    ));
                }
            }

            return [];
        }, []);
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    protected function theme(): Theme
    {
        return Theme::parse($this->setting('theme'));
    }

    protected function locale(): string
    {
        return $this->stringSetting('locale')
            ?? Guard::value(fn (): string => $this->app->getLocale(), 'en');
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
            Guard::value(fn (): string => $this->app->basePath(), ''),
            $this->intSetting('details.stack_frames', 12),
        );
    }

    protected function debugEnvironment(): bool
    {
        if ($this->config->get('app.debug') === true) {
            return true;
        }

        /** @var list<string> $environments */
        $environments = $this->setting('details.environments') ?? [];

        return Guard::value(fn (): bool => in_array($this->app->environment(), $environments, true), false);
    }

    /*
    |--------------------------------------------------------------------------
    | Config helpers
    |--------------------------------------------------------------------------
    */

    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->config->get('janitor.'.$key, $default);
    }

    protected function intSetting(string $key, int $default): int
    {
        $value = $this->setting($key);

        return is_int($value) ? $value : $default;
    }

    protected function stringSetting(string $key): ?string
    {
        $value = $this->setting($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
