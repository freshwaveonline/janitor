<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Enums\Theme;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/missing', fn () => throw new NotFoundHttpException);
        Route::get('/forbidden', fn () => abort(403));
        Route::get('/boom', fn () => throw new RuntimeException('kaboom'));
    });
});

/*
|--------------------------------------------------------------------------
| Translations
|--------------------------------------------------------------------------
*/

it('renders the page in the application locale', function (): void {
    app()->setLocale('nl');

    $this->get('/missing')
        ->assertSee('We konden deze pagina niet vinden')
        ->assertSee('Wat je kunt doen')
        ->assertSee('Meldingsnummer')
        ->assertSee('lang="nl"', false);
});

it('can be pinned to one locale regardless of the app locale', function (): void {
    app()->setLocale('nl');
    config()->set('janitor.locale', 'en');

    $this->get('/missing')->assertSee('We could not find this page');
});

it('substitutes the support address into the copy', function (): void {
    config()->set('janitor.links.support_email', 'help@acme.test');

    $this->get('/forbidden')->assertSee('help@acme.test');
});

it('only shows the support address for the configured codes', function (): void {
    config()->set('janitor.links.support_email', 'help@acme.test');
    config()->set('janitor.links.support_email_codes', [500]);

    $this->get('/missing')->assertDontSee('help@acme.test');
    $this->get('/boom')->assertSee('help@acme.test');
});

it('pre-fills the support mail with the message number', function (): void {
    config()->set('janitor.links.support_email', 'help@acme.test');

    $response = $this->get('/boom');
    $number = $response->headers->get('X-Message-Number');

    $response->assertSee('mailto:help@acme.test', false)
        ->assertSee(rawurlencode($number), false);
});

/*
|--------------------------------------------------------------------------
| Theme & colours
|--------------------------------------------------------------------------
*/

it('emits both colour schemes in auto mode', function (): void {
    config()->set('janitor.theme', Theme::Auto);

    $this->get('/missing')
        ->assertSee('prefers-color-scheme: dark', false)
        ->assertSee('[data-jn-theme="dark"]', false)
        ->assertSee('color-scheme: light dark', false);
});

it('emits only the dark palette when the theme is forced to dark', function (): void {
    config()->set('janitor.theme', Theme::Dark);

    $this->get('/missing')
        ->assertDontSee('prefers-color-scheme: dark', false)
        ->assertSee('color-scheme: dark', false);
});

it('carries the configured primary colour into the page', function (): void {
    config()->set('janitor.colors.primary', '#b91c1c');

    $this->get('/missing')->assertSee('--jn-primary: #b91c1c', false);
});

it('lets the light and dark overrides win over the primary colour', function (): void {
    config()->set('janitor.colors', [
        'primary' => '#4f46e5',
        'light' => '#b91c1c',
        'dark' => '#f87171',
        'auto_contrast' => false,
    ]);

    $this->get('/missing')
        ->assertSee('--jn-primary: #b91c1c', false)
        ->assertSee('--jn-primary: #f87171', false);
});

/*
|--------------------------------------------------------------------------
| Branding
|--------------------------------------------------------------------------
*/

it('falls back to the app name when no brand is configured', function (): void {
    $this->get('/missing')->assertSee('Acme');
});

it('renders a configured logo', function (): void {
    config()->set('janitor.brand.logo', 'https://cdn.acme.test/logo.svg');

    $this->get('/missing')->assertSee('https://cdn.acme.test/logo.svg', false);
});

it('renders a separate dark-mode logo when one is configured', function (): void {
    config()->set('janitor.brand.logo', '/logo-light.svg');
    config()->set('janitor.brand.logo_dark', '/logo-dark.svg');

    $this->get('/missing')
        ->assertSee('/logo-light.svg', false)
        ->assertSee('/logo-dark.svg', false)
        ->assertSee('jn-brand--dark', false);
});

/*
|--------------------------------------------------------------------------
| Call-to-action buttons
|--------------------------------------------------------------------------
*/

it('renders the actions configured for a status code', function (): void {
    config()->set('janitor.actions.404', ['home']);

    $this->get('/missing')
        ->assertSee('Go to home page')
        // The "go back" button is gone; the phrase still appears in the
        // suggestion list, so assert on the button's own marker.
        ->assertDontSee('data-jn-action="back"', false);
});

it('drops actions that cannot resolve', function (): void {
    // No support address configured, so no support button.
    config()->set('janitor.actions.404', ['support', 'home']);
    config()->set('janitor.links.support_email', null);

    $this->get('/missing')
        ->assertDontSee('Contact support')
        ->assertSee('Go to home page');
});

it('supports inline custom actions', function (): void {
    config()->set('janitor.actions.404', [[
        'label' => 'Browse the catalogue',
        'url' => '/products',
        'icon' => 'magnifying-glass',
        'style' => 'ghost',
    ]]);

    $this->get('/missing')
        ->assertSee('Browse the catalogue')
        ->assertSee('href="/products"', false);
});

it('always gives exactly one button the primary emphasis', function (): void {
    config()->set('janitor.actions.404', ['back', 'home']);

    $content = $this->get('/missing')->getContent();

    // Count the rendered class attribute, not the stylesheet's rules.
    expect(substr_count($content, 'class="jn-btn jn-btn--primary"'))->toBe(1)
        ->and(substr_count($content, 'class="jn-btn jn-btn--secondary"'))->toBe(1);
});

it('opens external actions in a new tab', function (): void {
    config()->set('janitor.links.status_page', 'https://status.acme.test');
    config()->set('janitor.actions.404', ['status_page']);

    $this->get('/missing')->assertSee('rel="noopener noreferrer"', false);
});

/*
|--------------------------------------------------------------------------
| Accessibility & structure
|--------------------------------------------------------------------------
*/

it('renders a single h1 and a labelled main region', function (): void {
    $content = $this->get('/missing')->getContent();

    expect(substr_count($content, '<h1'))->toBe(1)
        ->and($content)->toContain('role="main"');
});

it('inlines every icon so the page needs no external requests', function (): void {
    $content = $this->get('/missing')->getContent();

    expect($content)->toContain('<svg')
        ->and($content)->not->toContain('<script src=')
        ->and($content)->not->toContain('<link rel="stylesheet"');
});
