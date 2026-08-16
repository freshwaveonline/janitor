<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Enums\DetailVisibility;
use FreshwaveOnline\Janitor\ErrorPageRenderer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

beforeEach(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/boom', fn () => throw new RuntimeException('Database has gone away'));
        Route::get('/missing', fn () => throw new NotFoundHttpException);
        Route::get('/forbidden', fn () => abort(403, 'You may not edit a published post.'));
        Route::get('/throttled', fn () => throw new TooManyRequestsHttpException(90));
        Route::get('/teapot', fn () => throw new HttpException(418, 'I am a teapot'));
        Route::get('/guest', fn () => throw new AuthenticationException);
        Route::get('/invalid', fn () => throw ValidationException::withMessages(['email' => 'Invalid']));
    });
});

it('renders a styled page for a 404', function (): void {
    $response = $this->get('/missing');

    $response->assertStatus(404)
        ->assertSee('We could not find this page')
        ->assertSee('What you can do')
        ->assertSee('Message number');
});

it('renders a 500 without leaking the exception message by default', function (): void {
    config()->set('janitor.details.visibility', DetailVisibility::Never);

    $response = $this->get('/boom');

    $response->assertStatus(500)
        ->assertSee('Something went wrong on our side')
        ->assertDontSee('Database has gone away');
});

it('shows the exception and a copy button when the environment allows it', function (): void {
    config()->set('janitor.details.visibility', DetailVisibility::Always);

    $response = $this->get('/boom');

    $response->assertStatus(500)
        ->assertSee('Database has gone away')
        ->assertSee('Technical details')
        ->assertSee('data-jn-copy-from', false);
});

it('never shows the exception when visibility is never, whatever the environment', function (): void {
    config()->set('app.debug', true);
    config()->set('janitor.details.visibility', DetailVisibility::Never);

    $this->get('/boom')->assertDontSee('Database has gone away');
});

it('shows a deliberate abort message but not a framework one', function (): void {
    $this->get('/forbidden')
        ->assertStatus(403)
        ->assertSee('You may not edit a published post.');

    // 404 is not in `use_exception_message_codes`, so a ModelNotFoundException
    // cannot leak your model class names onto the page.
    $this->get('/missing')->assertDontSee('No query results');
});

it('puts the message number and request id in the response headers', function (): void {
    $response = $this->withHeader('X-Request-Id', 'req-abc-123')->get('/missing');

    $response->assertHeader('X-Request-Id', 'req-abc-123');
    expect($response->headers->get('X-Message-Number'))->toStartWith('ERR-');
});

it('shows the same message number on the page as in the header', function (): void {
    $response = $this->get('/boom');
    $number = $response->headers->get('X-Message-Number');

    expect($number)->not->toBeNull();
    $response->assertSee($number);
});

it('gives two requests to the same failure the same message number', function (): void {
    $first = $this->get('/boom')->headers->get('X-Message-Number');
    $second = $this->get('/boom')->headers->get('X-Message-Number');

    expect($first)->toBe($second);
});

it('gives different failures different message numbers', function (): void {
    expect($this->get('/boom')->headers->get('X-Message-Number'))
        ->not->toBe($this->get('/missing')->headers->get('X-Message-Number'));
});

it('shows a retry moment and a countdown for a 429', function (): void {
    $response = $this->get('/throttled');

    $response->assertStatus(429)
        ->assertSee('When to try again')
        ->assertSee('data-jn-countdown', false)
        ->assertHeader('Retry-After');
});

it('marks the retry button as waiting without disabling it server-side', function (): void {
    // Progressive enhancement: the countdown script disables it on load and
    // releases it at zero. Without JavaScript the button still works, which
    // beats a button that can never be pressed.
    $content = $this->get('/throttled')->getContent();

    expect($content)->toContain('data-jn-wait-for-retry')
        ->and($content)->not->toContain('disabled data-jn-wait-for-retry');
});

it('falls back to the family copy for a status without its own translation', function (): void {
    $this->get('/teapot')
        ->assertStatus(418)
        ->assertSee('This request could not be completed');
});

it('leaves validation exceptions to Laravel', function (): void {
    // Rendering a full-screen error page here would break every form in the app.
    $this->from('/invalid')->post('/invalid')->assertStatus(405);
    $this->get('/invalid')->assertRedirect();
});

it('leaves authentication exceptions to Laravel when a login route exists', function (): void {
    Route::middleware('web')->get('/login', fn () => 'login')->name('login');

    $this->get('/guest')->assertRedirect('/login');
});

it('renders a 401 page when the app has no login route', function (): void {
    $this->get('/guest')
        ->assertStatus(401)
        ->assertSee('You need to sign in');
});

it('renders JSON for requests that expect it', function (): void {
    $response = $this->getJson('/boom');

    $response->assertStatus(500)
        ->assertJsonStructure(['message', 'title', 'status', 'message_number', 'request_id']);

    expect($response->json('status'))->toBe(500);
});

it('includes the retry moment in the JSON response', function (): void {
    $this->getJson('/throttled')
        ->assertStatus(429)
        ->assertJsonStructure(['retry_after', 'retry_at']);
});

it('marks error pages as noindex', function (): void {
    $this->get('/missing')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('noindex, nofollow');
});

it('hands the exception back to Laravel when the package is disabled', function (): void {
    config()->set('janitor.enabled', false);

    $this->get('/missing')
        ->assertStatus(404)
        ->assertDontSee('What you can do');
});

it('skips status codes that are excluded', function (): void {
    config()->set('janitor.except_codes', [404]);

    $this->get('/missing')->assertDontSee('What you can do');
});

it('defers to the application when it has its own errors view', function (): void {
    // A developer who wrote resources/views/errors/404.blade.php meant it, so
    // the package steps aside and lets Laravel render that view.
    $directory = resource_path('views/errors');
    $view = $directory.'/404.blade.php';
    $created = ! is_dir($directory);

    if ($created) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($view, 'Our own 404.');
    $this->app['view']->flushFinderCache();

    try {
        $this->get('/missing')
            ->assertStatus(404)
            ->assertSee('Our own 404.')
            ->assertDontSee('What you can do');
    } finally {
        unlink($view);

        if ($created) {
            rmdir($directory);
        }
    }
});

it('takes over even with an application view when the option is off', function (): void {
    config()->set('janitor.views.prefer_application_views', false);

    expect($this->app->make(ErrorPageRenderer::class)
        ->shouldHandle(request(), new NotFoundHttpException))->toBeTrue();
});

it('keeps the exception headers on the rendered response', function (): void {
    Route::middleware('web')->get('/locked', fn () => throw new HttpException(423, 'Locked', null, ['X-Lock-Owner' => 'alice']));

    $this->get('/locked')->assertHeader('X-Lock-Owner', 'alice');
});
