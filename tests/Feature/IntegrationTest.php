<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Enums\DetailVisibility;
use FreshwaveOnline\Janitor\ErrorPageRenderer;
use FreshwaveOnline\Janitor\Support\Icons;
use FreshwaveOnline\Janitor\Support\MessageNumber;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/ok', fn () => 'fine');
        Route::get('/missing', fn () => throw new NotFoundHttpException);
    });
});

/*
|--------------------------------------------------------------------------
| Request id middleware
|--------------------------------------------------------------------------
*/

it('adds a request id to successful responses too', function (): void {
    // Same id in the log and on the error page only works if it is assigned at
    // the start of every request, not when an error happens.
    $response = $this->get('/ok');

    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
});

it('echoes an upstream request id back unchanged', function (): void {
    $this->withHeader('X-Request-Id', 'edge-9f2a')
        ->get('/ok')
        ->assertHeader('X-Request-Id', 'edge-9f2a');
});

it('can use a different response header', function (): void {
    config()->set('janitor.request_id.response_header', 'X-Trace-Id');

    $response = $this->get('/missing');

    expect($response->headers->get('X-Trace-Id'))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Service container
|--------------------------------------------------------------------------
*/

it('registers the package services as singletons', function (): void {
    expect($this->app->make(ErrorPageRenderer::class))->toBe($this->app->make(ErrorPageRenderer::class))
        ->and($this->app->make('janitor'))->toBeInstanceOf(ErrorPageRenderer::class);
});

it('builds the message number generator from config', function (): void {
    config()->set('janitor.message_number.prefix', 'ACME');

    // Singletons are resolved once, so re-bind for the changed config.
    $this->app->forgetInstance(MessageNumber::class);

    expect($this->app->make(MessageNumber::class)->for(new RuntimeException, 500))
        ->toStartWith('ACME-');
});

it('publishes the config, views and translations under their own tags', function (): void {
    $groups = ServiceProvider::$publishGroups;

    expect($groups)->toHaveKeys(['janitor', 'janitor-config', 'janitor-views', 'janitor-lang']);
});

/*
|--------------------------------------------------------------------------
| Blade directives
|--------------------------------------------------------------------------
*/

it('renders the pop-up handler through the blade directive', function (): void {
    $this->blade('@janitorScripts')->assertSee('jn-modal-root', false);
});

it('renders an icon through the blade directive', function (): void {
    $this->blade("@janitorIcon('home')")
        ->assertSee('<svg', false)
        ->assertSee('aria-hidden="true"', false);
});

/*
|--------------------------------------------------------------------------
| Icons
|--------------------------------------------------------------------------
*/

it('has an icon for every status code it writes copy for', function (int $status): void {
    expect(Icons::exists(Icons::forStatus($status)))->toBeTrue();
})->with([400, 401, 402, 403, 404, 405, 408, 409, 410, 413, 419, 423, 429, 500, 501, 502, 503, 504, 418, 599]);

it('escapes anything it puts into an svg attribute', function (): void {
    $svg = Icons::svg('home', ['class' => '"><script>alert(1)</script>'])->toHtml();

    expect($svg)->not->toContain('<script>')
        ->and($svg)->toContain('&quot;&gt;&lt;script&gt;');
});

it('falls back to a generic icon for an unknown name', function (): void {
    expect(Icons::svg('does-not-exist')->toHtml())->toContain('<svg')
        ->and(Icons::path('does-not-exist'))->toBe(Icons::path('exclamation-circle'));
});

/*
|--------------------------------------------------------------------------
| Escaping
|--------------------------------------------------------------------------
*/

it('escapes the exception message before it reaches the page', function (): void {
    Route::middleware('web')->get('/xss', fn () => throw new RuntimeException('<script>alert("xss")</script>'));

    config()->set('janitor.details.visibility', DetailVisibility::Always);

    $content = $this->get('/xss')->getContent();

    expect($content)->not->toContain('<script>alert("xss")</script>')
        ->and($content)->toContain('&lt;script&gt;');
});

it('escapes the copyable report payload', function (): void {
    Route::middleware('web')->get('/xss', fn () => throw new RuntimeException('</script><script>alert(1)</script>'));

    config()->set('janitor.details.visibility', DetailVisibility::Always);

    $content = $this->get('/xss')->getContent();

    // The JSON payload must not be able to close its own <script> element.
    expect($content)->not->toContain('</script><script>alert(1)</script>');
});
