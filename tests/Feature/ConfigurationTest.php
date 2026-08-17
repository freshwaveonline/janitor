<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Enums\DetailVisibility;
use FreshwaveOnline\Janitor\Enums\LivewireErrorMode;
use FreshwaveOnline\Janitor\Enums\ModalPosition;
use FreshwaveOnline\Janitor\Enums\Theme;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
|
| The shipped defaults, what publishing produces, and the environment
| variables that change them without touching a file.
|
*/

beforeEach(function (): void {
    Route::middleware('web')->get('/missing', fn () => throw new NotFoundHttpException);
});

it('ships a default for every section the package reads', function (string $key): void {
    expect(config()->has('janitor.'.$key))->toBeTrue();
})->with([
    'enabled', 'codes', 'except_codes', 'except_exceptions', 'handle_missing_login_route',
    'brand', 'colors', 'theme', 'links', 'actions', 'messages', 'message_number',
    'request_id', 'retry_after', 'details', 'livewire', 'json', 'filament', 'preview',
    'views', 'noindex', 'show_timestamp', 'locale',
]);

it('defaults to values that are safe in production', function (): void {
    expect(config('janitor.enabled'))->toBeTrue()
        ->and(config('janitor.codes'))->toBe(['*'])
        // Validation belongs in the form, not on a full-screen page.
        ->and(config('janitor.except_codes'))->toBe([422])
        // Auto means "only where the environment says it is safe".
        ->and(config('janitor.details.visibility'))->toBe(DetailVisibility::Auto)
        ->and(config('janitor.details.environments'))->not->toContain('production')
        ->and(config('janitor.noindex'))->toBeTrue()
        // Null means local-only for a route that renders stack traces.
        ->and(config('janitor.preview.enabled'))->toBeNull()
        // Read from the file: the suite itself flips this one on so it can
        // exercise the package's own 5xx page instead of Ignition's.
        ->and((require __DIR__.'/../../config/janitor.php')['details']['replace_debug_page'])->toBeFalse();
});

it('ships enum defaults rather than loose strings', function (): void {
    expect(config('janitor.theme'))->toBe(Theme::Auto)
        ->and(config('janitor.livewire.mode'))->toBe(LivewireErrorMode::Modal)
        ->and(config('janitor.livewire.position'))->toBe(ModalPosition::BottomRight);
});

it('leaves the accent unset so a panel can supply it', function (): void {
    // Palette falls back to the same colour, so the rendered default is
    // unchanged; null is what makes "nothing was chosen" expressible.
    expect(config('janitor.colors.primary'))->toBeNull();

    $this->get('/missing')->assertSee('--jn-primary: #4f46e5', false);
});

it('publishes a config file that matches the packaged one', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'janitor-config', '--force' => true])->assertSuccessful();

    $published = config_path('janitor.php');

    expect(File::exists($published))->toBeTrue()
        ->and(File::get($published))->toBe(File::get(__DIR__.'/../../config/janitor.php'));

    File::delete($published);
});

it('reads each environment variable it documents', function (string $variable, string $key, string $value, mixed $expected): void {
    putenv($variable.'='.$value);
    $_ENV[$variable] = $value;

    try {
        $fresh = require __DIR__.'/../../config/janitor.php';

        expect(data_get($fresh, $key))->toBe($expected);
    } finally {
        putenv($variable);
        unset($_ENV[$variable]);
    }
})->with([
    'enabled' => ['JANITOR_ENABLED', 'enabled', 'false', false],
    'primary colour' => ['JANITOR_PRIMARY', 'colors.primary', '#ff0000', '#ff0000'],
    'light override' => ['JANITOR_PRIMARY_LIGHT', 'colors.light', '#00ff00', '#00ff00'],
    'dark override' => ['JANITOR_PRIMARY_DARK', 'colors.dark', '#0000ff', '#0000ff'],
    'preview' => ['JANITOR_PREVIEW', 'preview.enabled', 'true', true],
    'debug page' => ['JANITOR_REPLACE_DEBUG_PAGE', 'details.replace_debug_page', 'true', true],
]);

it('turns the whole package off from config', function (): void {
    config()->set('janitor.enabled', false);

    $response = $this->get('/missing');

    $response->assertStatus(404);
    expect($response->getContent())->not->toContain('jn-card');
});

it('limits itself to an explicit list of codes', function (): void {
    config()->set('janitor.views.prefer_application_views', false);
    config()->set('janitor.codes', [404]);

    Route::middleware('web')->get('/boom', fn () => throw new RuntimeException('kaboom'));

    expect($this->get('/missing')->getContent())->toContain('jn-card')
        ->and($this->get('/boom')->getContent())->not->toContain('jn-card');
});

it('applies the theme from config', function (Theme $theme, string $expected, string $absent): void {
    config()->set('janitor.views.prefer_application_views', false);
    config()->set('janitor.theme', $theme);

    $content = $this->get('/missing')->getContent();

    expect($content)->toContain($expected)->not->toContain($absent);
})->with([
    'forced light' => [Theme::Light, 'color-scheme: light', 'prefers-color-scheme'],
    'forced dark' => [Theme::Dark, 'color-scheme: dark', 'prefers-color-scheme'],
]);

it('honours the retry window from config', function (): void {
    config()->set('janitor.views.prefer_application_views', false);
    config()->set('janitor.retry_after.max_seconds', 30);

    Route::middleware('web')->get('/slow', fn () => throw new TooManyRequestsHttpException(600));

    // 600s is past the configured ceiling, so the retry block never renders.
    // Every class name also appears in the inlined stylesheet, so match the
    // rendered element rather than the bare class.
    expect($this->get('/slow')->getContent())
        ->not->toContain('<section class="jn-block jn-block--retry"')
        ->not->toContain('data-jn-countdown="');
});

it('turns the countdown off without losing the retry moment', function (): void {
    config()->set('janitor.views.prefer_application_views', false);
    config()->set('janitor.retry_after.countdown', false);

    Route::middleware('web')->get('/slow', fn () => throw new TooManyRequestsHttpException(90));

    $response = $this->get('/slow');

    $response->assertHeader('Retry-After', '90');

    expect($response->getContent())->toContain('<section class="jn-block jn-block--retry"')
        ->not->toContain('data-jn-countdown="');
});

it('drops the request id entirely when it is switched off', function (): void {
    config()->set('janitor.request_id.enabled', false);

    $response = $this->get('/missing');

    expect($response->headers->has('X-Request-Id'))->toBeFalse();
});

it('drops the message number entirely when it is switched off', function (): void {
    config()->set('janitor.message_number.enabled', false);

    $response = $this->get('/missing');

    expect($response->headers->has('X-Message-Number'))->toBeFalse();
});

it('renames the response headers from config', function (): void {
    config()->set('janitor.request_id.response_header', 'X-Trace');
    config()->set('janitor.message_number.response_header', 'X-Incident');

    $this->get('/missing')->assertHeader('X-Trace')->assertHeader('X-Incident');
});

it('takes the actions for a status straight from config', function (): void {
    config()->set('janitor.views.prefer_application_views', false);
    config()->set('janitor.actions.404', ['reload']);

    $content = $this->get('/missing')->getContent();

    expect($content)->toContain('data-jn-action="reload"')
        ->and($content)->not->toContain('data-jn-action="back"');
});

it('turns the noindex tag off from config', function (): void {
    config()->set('janitor.views.prefer_application_views', false);
    config()->set('janitor.noindex', false);

    $response = $this->get('/missing');

    expect($response->headers->has('X-Robots-Tag'))->toBeFalse()
        ->and($response->getContent())->not->toContain('noindex, nofollow');
});
