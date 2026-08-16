<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Vvdboogaard\ErrorPages\Enums\LivewireErrorMode;

/**
 * Appends the (inline, ~3 KB) Livewire error handler just before `</body>`.
 *
 * Same trick Livewire itself uses. Opt out with `livewire.inject_assets` and
 * place `@errorPagesScripts` in your layout instead.
 */
class InjectErrorPagesAssets
{
    public function __construct(
        private readonly Config $config,
        private readonly ViewFactory $views,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || ! str_contains($content, '</body>')) {
            return $response;
        }

        $script = $this->views->make('error-pages::partials.livewire-script')->render();

        // Replace the final closing tag only: a nested `</body>` inside a code
        // sample on the page must not swallow the script.
        $position = strripos($content, '</body>');

        $response->setContent(
            substr($content, 0, $position).$script.substr($content, $position)
        );

        return $response;
    }

    protected function shouldInject(Request $request, Response $response): bool
    {
        if ($this->config->get('error-pages.enabled') !== true) {
            return false;
        }

        if ($this->config->get('error-pages.livewire.inject_assets') !== true) {
            return false;
        }

        if (LivewireErrorMode::parse($this->config->get('error-pages.livewire.mode')) === LivewireErrorMode::Disabled) {
            return false;
        }

        if ($request->attributes->get('error-pages.assets_injected') === true) {
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
