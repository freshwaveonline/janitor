<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor;

use FreshwaveOnline\Janitor\Contracts\ErrorContextBuilder;
use FreshwaveOnline\Janitor\Contracts\ErrorRenderer;
use FreshwaveOnline\Janitor\Data\ErrorContext;
use FreshwaveOnline\Janitor\Enums\LivewireErrorMode;
use FreshwaveOnline\Janitor\Support\Icons;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Decides whether this package renders a given exception, and in which shape.
 *
 * Registered as a `renderable` callback on Laravel's exception handler, so
 * returning null anywhere below hands the exception straight back to the
 * framework — the safe default whenever we are unsure.
 */
class ErrorPageRenderer implements ErrorRenderer
{
    public function __construct(
        protected readonly Application $app,
        protected readonly Config $config,
        protected readonly ViewFactory $views,
        protected readonly ErrorContextBuilder $factory,
    ) {}

    public function render(Request $request, Throwable $exception): ?SymfonyResponse
    {
        if (! $this->shouldHandle($request, $exception)) {
            return null;
        }

        $status = $this->statusFor($exception);
        $context = $this->factory->make($request, $exception, $status);

        $response = $this->respond($request, $context);

        return $this->withHeaders($response, $context, $exception);
    }

    /**
     * Render a context directly — used by the preview routes and available for
     * anyone wanting an error page outside of the exception handler.
     */
    public function renderContext(Request $request, ErrorContext $context): SymfonyResponse
    {
        return $this->withHeaders($this->respond($request, $context), $context, null);
    }

    /*
    |--------------------------------------------------------------------------
    | Should we handle this?
    |--------------------------------------------------------------------------
    */

    public function shouldHandle(Request $request, Throwable $exception): bool
    {
        if ($this->config->get('janitor.enabled') !== true) {
            return false;
        }

        if ($this->isExcludedException($exception)) {
            return false;
        }

        $status = $this->statusFor($exception);

        if (! $this->handlesStatus($status)) {
            return false;
        }

        // Locally, Ignition/Whoops is a far better tool than any styled page.
        // Opt in via `details.replace_debug_page` to design against these pages.
        if ($status >= 500
            && $this->config->get('app.debug') === true
            && $this->config->get('janitor.details.replace_debug_page') !== true) {
            return false;
        }

        if ($this->prefersApplicationView($status)) {
            return false;
        }

        return true;
    }

    public function statusFor(Throwable $exception): int
    {
        if ($exception instanceof AuthenticationException) {
            return 401;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return $status >= 400 && $status <= 599 ? $status : 500;
        }

        return 500;
    }

    public function isLivewireRequest(Request $request): bool
    {
        return $request->hasHeader('X-Livewire')
            || $request->is('livewire/update', 'livewire/message/*');
    }

    /**
     * @return array<string, mixed>
     */
    public function livewirePayload(ErrorContext $context): array
    {
        return [
            ...$context->toArray(),
            'support_mailto' => $context->supportMailto($this->stringConfig('links.support_subject')),
            'copy_report' => $context->details !== null
                ? $context->copyReport($this->arrayConfig('details.copy_includes'))
                : null,
            // Only the icons this error actually uses, so the injected handler
            // ships no icon data of its own.
            'icons' => $this->iconPaths($context),
            'labels' => [
                'message_number' => (string) __('janitor::ui.meta.message_number'),
                'request_id' => (string) __('janitor::ui.meta.request_id'),
                'copy' => (string) __('janitor::ui.actions.copy'),
                'copied' => (string) __('janitor::ui.actions.copied'),
                'dismiss' => (string) __('janitor::ui.actions.dismiss'),
                'retry_at' => (string) __('janitor::ui.retry.at'),
                'retry_in' => (string) __('janitor::ui.retry.in'),
                'details' => (string) __('janitor::ui.details.heading'),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Views & headers
    |--------------------------------------------------------------------------
    */

    /**
     * A published `vendor/janitor/errors/404.blade.php` wins over the
     * shared page, so one status code can get a bespoke design.
     */
    public function viewName(int $status): string
    {
        $specific = "janitor::errors.{$status}";

        if ($this->views->exists($specific)) {
            return $specific;
        }

        // With a layout configured the card is embedded in the application's own
        // chrome rather than rendered as a standalone document.
        $layout = $this->config->get('janitor.views.layout');

        if (is_string($layout) && $layout !== '' && $this->views->exists($layout)) {
            return 'janitor::embedded';
        }

        $configured = $this->config->get('janitor.views.page');

        return is_string($configured) && $configured !== '' ? $configured : 'janitor::error';
    }

    protected function respond(Request $request, ErrorContext $context): SymfonyResponse
    {
        if ($this->isLivewireRequest($request)) {
            $livewire = $this->livewireResponse($context);

            if ($livewire !== null) {
                return $livewire;
            }
        }

        if ($this->wantsJson($request)) {
            return $this->jsonResponse($context);
        }

        return new Response(
            $this->views->make($this->viewName($context->statusCode), ['error' => $context])->render(),
            $context->statusCode,
        );
    }

    protected function isExcludedException(Throwable $exception): bool
    {
        /** @var list<class-string> $excluded */
        $excluded = $this->config->get('janitor.except_exceptions', []);

        foreach ($excluded as $class) {
            if (! $exception instanceof $class) {
                continue;
            }

            // Laravel's AuthenticationException handler redirects to route('login').
            // Without that route it throws RouteNotFoundException and the visitor
            // gets a 500 instead of a 401 — a case worth taking over.
            if ($this->isRecoverableAuthenticationException($exception)) {
                return false;
            }

            return true;
        }

        return false;
    }

    protected function isRecoverableAuthenticationException(Throwable $exception): bool
    {
        if (! $exception instanceof AuthenticationException) {
            return false;
        }

        if ($this->config->get('janitor.handle_missing_login_route') !== true) {
            return false;
        }

        $route = $this->config->get('janitor.links.login_route', 'login');

        return ! (is_string($route) && $route !== '' && Route::has($route));
    }

    protected function handlesStatus(int $status): bool
    {
        if ($status < 400 || $status > 599) {
            return false;
        }

        /** @var list<int> $except */
        $except = $this->config->get('janitor.except_codes', []);

        if (in_array($status, $except, true)) {
            return false;
        }

        /** @var list<int|string> $codes */
        $codes = $this->config->get('janitor.codes', ['*']);

        return in_array('*', $codes, true) || in_array($status, $codes, true);
    }

    /**
     * Respect an application's own `resources/views/errors/{code}.blade.php`.
     * Someone who bothered to write that view meant it.
     */
    protected function prefersApplicationView(int $status): bool
    {
        if ($this->config->get('janitor.views.prefer_application_views') !== true) {
            return false;
        }

        return $this->views->exists("errors.{$status}");
    }

    /*
    |--------------------------------------------------------------------------
    | Shapes
    |--------------------------------------------------------------------------
    */

    protected function wantsJson(Request $request): bool
    {
        if ($this->config->get('janitor.json.enabled') !== true) {
            return false;
        }

        return $request->expectsJson();
    }

    protected function jsonResponse(ErrorContext $context): JsonResponse
    {
        $payload = [
            'message' => $context->message,
            'title' => $context->title,
            'status' => $context->statusCode,
        ];

        if ($context->reason !== null) {
            $payload['reason'] = $context->reason;
        }

        if ($this->config->get('janitor.json.include_message_number') === true && $context->messageNumber !== null) {
            $payload['message_number'] = $context->messageNumber;
        }

        if ($this->config->get('janitor.json.include_request_id') === true && $context->requestId !== null) {
            $payload['request_id'] = $context->requestId;
        }

        if ($this->config->get('janitor.json.include_retry_after') === true && $context->retryAt !== null) {
            $payload['retry_after'] = $context->retryInSeconds();
            $payload['retry_at'] = $context->retryAt->toIso8601String();
        }

        if ($this->config->get('janitor.json.include_details') === true && $context->details !== null) {
            $payload['exception'] = $context->details->toArray();
        }

        return new JsonResponse($payload, $context->statusCode);
    }

    /**
     * Livewire intercepts failed round-trips itself. We hand it a payload our
     * injected handler recognises (modal), the full page HTML (page), or
     * nothing at all (disabled), in which case Livewire's own overlay appears.
     */
    protected function livewireResponse(ErrorContext $context): ?SymfonyResponse
    {
        $mode = LivewireErrorMode::parse($this->config->get('janitor.livewire.mode'));

        if ($mode === LivewireErrorMode::Disabled) {
            return null;
        }

        if ($mode === LivewireErrorMode::Page) {
            return new Response(
                $this->views->make($this->viewName($context->statusCode), ['error' => $context])->render(),
                $context->statusCode,
            );
        }

        return new JsonResponse([
            'message' => $context->message,
            'janitor' => $this->livewirePayload($context),
        ], $context->statusCode);
    }

    /**
     * @return array<string, string>
     */
    protected function iconPaths(ErrorContext $context): array
    {
        $names = [$context->icon, 'x-mark', 'clipboard', 'hashtag', 'fingerprint'];

        foreach ($context->actions as $action) {
            if ($action->icon !== null) {
                $names[] = $action->icon;
            }
        }

        if ($context->retryAt !== null) {
            $names[] = 'clock';
        }

        $paths = [];

        foreach (array_unique($names) as $name) {
            $paths[$name] = Icons::path($name);
        }

        return $paths;
    }

    protected function withHeaders(SymfonyResponse $response, ErrorContext $context, ?Throwable $exception): SymfonyResponse
    {
        // Keep whatever the exception asked for: WWW-Authenticate, Allow,
        // Retry-After — dropping those would change the HTTP semantics.
        if ($exception instanceof HttpExceptionInterface) {
            foreach ($exception->getHeaders() as $name => $value) {
                if (is_string($name) && (is_string($value) || is_array($value))) {
                    $response->headers->set($name, $value);
                }
            }
        }

        $requestHeader = $this->stringConfig('request_id.response_header');

        if ($requestHeader !== null && $context->requestId !== null) {
            $response->headers->set($requestHeader, $context->requestId);
        }

        $messageHeader = $this->stringConfig('message_number.response_header');

        if ($messageHeader !== null && $context->messageNumber !== null) {
            $response->headers->set($messageHeader, $context->messageNumber);
        }

        if ($context->retryAt !== null && ! $response->headers->has('Retry-After')) {
            $response->headers->set('Retry-After', (string) $context->retryInSeconds());
        }

        // Error pages must never end up in a search index or a shared cache.
        if ($this->config->get('janitor.noindex') === true) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    protected function stringConfig(string $key): ?string
    {
        $value = $this->config->get('janitor.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, bool>
     */
    protected function arrayConfig(string $key): array
    {
        $value = $this->config->get('janitor.'.$key);

        /** @var array<string, bool> $value */
        return is_array($value) ? $value : [];
    }
}
