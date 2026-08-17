<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Http\Middleware;

use Closure;
use FreshwaveOnline\Janitor\Enums\LivewireErrorMode;
use FreshwaveOnline\Janitor\Support\Guard;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Appends the (inline, ~3 KB) Livewire error handler just before `</body>`.
 *
 * Same trick Livewire itself uses. Opt out with `livewire.inject_assets` and
 * place `@janitorScripts` in your layout instead.
 */
class InjectJanitorAssets
{
    public function __construct(
        private readonly Config $config,
        private readonly ViewFactory $views,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! Guard::value(fn (): bool => $this->shouldInject($request, $response), false)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            return $response;
        }

        // Inject before the final closing tag only: a nested `</body>` inside a
        // code sample on the page must not swallow the script.
        $position = strripos($content, '</body>');

        if ($position === false) {
            return $response;
        }

        // This middleware runs on responses that succeeded. A partial that
        // cannot render is a reason to ship the page without the handler, never
        // a reason to turn a working page into a 500.
        $script = Guard::value(fn (): string => $this->views->make('janitor::partials.livewire-script')->render(), '');

        if ($script === '') {
            return $response;
        }

        $response->setContent(
            substr($content, 0, $position).$script.substr($content, $position)
        );

        return $response;
    }

    protected function shouldInject(Request $request, Response $response): bool
    {
        if ($this->config->get('janitor.enabled') !== true) {
            return false;
        }

        if ($this->config->get('janitor.livewire.inject_assets') !== true) {
            return false;
        }

        if (LivewireErrorMode::parse($this->config->get('janitor.livewire.mode')) === LivewireErrorMode::Disabled) {
            return false;
        }

        if ($request->attributes->get('janitor.assets_injected') === true) {
            return false;
        }

        if (! class_exists(Livewire::class)) {
            return false;
        }

        if ($response->isRedirection() || $request->ajax()) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        return is_string($contentType) && str_contains(strtolower($contentType), 'text/html');
    }
}
