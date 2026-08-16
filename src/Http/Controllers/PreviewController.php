<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Http\Controllers;

use FreshwaveOnline\Janitor\Contracts\ErrorContextBuilder;
use FreshwaveOnline\Janitor\Contracts\ErrorRenderer;
use FreshwaveOnline\Janitor\Enums\DetailVisibility;
use FreshwaveOnline\Janitor\Enums\ModalPosition;
use FreshwaveOnline\Janitor\Enums\Theme;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Design against every error state without provoking real errors.
 *
 * `/_janitor` lists them, `/_janitor/{code}` renders one. Handy query
 * parameters: `?theme=dark`, `?retry=90`, `?details=1`, `?modal=1`.
 */
class PreviewController
{
    /**
     * Codes with a dedicated translation entry — the ones worth previewing.
     *
     * @var list<int>
     */
    private const CODES = [400, 401, 402, 403, 404, 405, 408, 409, 410, 413, 419, 423, 429, 500, 501, 502, 503, 504];

    public function __construct(
        private readonly Config $config,
        private readonly ViewFactory $views,
        private readonly ErrorContextBuilder $factory,
        private readonly ErrorRenderer $renderer,
    ) {}

    public function index(Request $request): View
    {
        $contexts = [];

        foreach (self::CODES as $code) {
            $contexts[$code] = $this->factory->make($request, $this->exceptionFor($code, $request), $code);
        }

        return $this->views->make('janitor::preview', [
            'contexts' => $contexts,
            'basePath' => trim((string) $this->config->get('janitor.preview.path', '_janitor'), '/'),
        ]);
    }

    public function show(Request $request, int $code): SymfonyResponse
    {
        abort_unless(in_array($code, self::CODES, true) || ($code >= 400 && $code <= 599), 404);

        $this->applyOverrides($request);

        $context = $this->factory->make($request, $this->exceptionFor($code, $request), $code);

        if ($request->boolean('modal')) {
            return new Response(
                $this->views->make('janitor::preview-modal', [
                    'error' => $context,
                    'payload' => $this->renderer->livewirePayload($context),
                ])->render()
            );
        }

        // Rendered with a 200 so the browser devtools stay usable while designing.
        return new Response(
            $this->views->make($this->renderer->viewName($code), ['error' => $context])->render()
        );
    }

    /**
     * Let query parameters temporarily override config, so one preview URL can
     * demonstrate light, dark, with-details and without.
     */
    private function applyOverrides(Request $request): void
    {
        $theme = $request->query('theme');

        if (is_string($theme) && Theme::tryFrom($theme) !== null) {
            $this->config->set('janitor.theme', Theme::from($theme));
        }

        if ($request->has('details')) {
            $this->config->set(
                'janitor.details.visibility',
                $request->boolean('details')
                    ? DetailVisibility::Always
                    : DetailVisibility::Never,
            );
        }

        $position = $request->query('position');

        if (is_string($position) && ModalPosition::tryFrom($position) !== null) {
            $this->config->set('janitor.livewire.position', ModalPosition::from($position));
        }
    }

    /**
     * A representative exception per code, so the message number and the stack
     * trace on the preview look like the real thing.
     */
    private function exceptionFor(int $code, Request $request): Throwable
    {
        $retryAfter = $request->integer('retry');

        return match (true) {
            $code === 429 => new TooManyRequestsHttpException(
                $retryAfter > 0 ? $retryAfter : 90,
                'Too Many Requests',
            ),
            $code === 503 => new ServiceUnavailableHttpException(
                $retryAfter > 0 ? $retryAfter : 900,
                'Service Unavailable',
            ),
            $code >= 500 => new RuntimeException('Preview: an unhandled exception reached the error handler.'),
            default => new HttpException($code, 'Preview'),
        };
    }
}
