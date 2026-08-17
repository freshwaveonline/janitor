<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Http\Middleware\InjectJanitorAssets;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Livewire is optional
|--------------------------------------------------------------------------
|
| The suite runs without Livewire installed, which is the situation that
| must not break: no class may be touched at load time, the handler is never
| injected, and a Livewire-shaped request still gets a real answer.
|
*/

beforeEach(function (): void {
    config()->set('janitor.views.prefer_application_views', false);

    Route::middleware('web')->get('/missing', fn () => throw new NotFoundHttpException);
    Route::middleware('web')->get('/fine', fn () => response('<html><body><p>Fine</p></body></html>'));
});

it('runs without Livewire installed at all', function (): void {
    expect(class_exists(Livewire::class))->toBeFalse();

    $this->get('/missing')->assertStatus(404)->assertSee('We could not find this page');
});

it('does not register the asset middleware without Livewire', function (): void {
    $kernel = app(Kernel::class);

    expect($kernel->hasMiddleware(InjectJanitorAssets::class))->toBeFalse();
});

it('leaves a successful page untouched without Livewire', function (): void {
    $content = $this->get('/fine')->assertOk()->getContent();

    expect($content)->toBe('<html><body><p>Fine</p></body></html>');
});

it('still answers a Livewire-shaped request when Livewire is absent', function (): void {
    // The header is all the renderer keys off, so this is what an application
    // that removed Livewire but kept a stale front-end would send.
    $response = $this->withHeader('X-Livewire', 'true')->get('/missing');

    $response->assertStatus(404);
    expect($response->json('janitor.status'))->toBe(404);
});

/**
 * Injection is gated on Livewire being installed, which it is not here. This
 * subclass opens that gate so the placement logic can be tested on its own.
 */
function injecting(): InjectJanitorAssets
{
    return new class(app('config'), app('view')) extends InjectJanitorAssets
    {
        protected function shouldInject(Request $request, Response $response): bool
        {
            return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
        }
    };
}

function inject(string $body, string $contentType = 'text/html'): string
{
    $response = injecting()->handle(
        Request::create('/fine'),
        fn (): Response => new HttpResponse($body, 200, ['Content-Type' => $contentType]),
    );

    return (string) $response->getContent();
}

it('appends the handler before the last closing body tag', function (): void {
    $content = inject('<html><body><pre>&lt;/body&gt;</pre><p>real</p></body></html>');

    expect($content)->toContain('__janitorLivewire')
        // The sample inside the page must not have swallowed the script.
        ->and(substr_count($content, '</body>'))->toBe(1)
        ->and($content)->toEndWith('</body></html>');
});

it('leaves a response with nothing to anchor to alone', function (): void {
    expect(inject('<p>fragment</p>'))->toBe('<p>fragment</p>');
});

it('leaves a response that is not HTML alone', function (): void {
    expect(inject('{"ok":true}', 'application/json'))->toBe('{"ok":true}');
});

it('carries the pop-up settings the handler reads', function (string $key, mixed $value, string $expected): void {
    config()->set('janitor.livewire.'.$key, $value);

    $script = view('janitor::partials.livewire-script')->render();

    expect($script)->toContain($expected);
})->with([
    'dismissible off' => ['dismissible', false, '"dismissible":false'],
    'dismissible on' => ['dismissible', true, '"dismissible":true'],
    'auto dismiss' => ['auto_dismiss', 4000, '"autoDismiss":4000'],
    'fetch interception' => ['intercept_fetch', true, '"interceptFetch":true'],
    'page mode' => ['mode', 'page', '"mode":"page"'],
    'disabled' => ['mode', 'disabled', '"mode":"disabled"'],
    'max width' => ['max_width', '32rem', '32rem'],
]);
