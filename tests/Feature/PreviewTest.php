<?php

declare(strict_types=1);

use Vvdboogaard\ErrorPages\Enums\Theme;
use Vvdboogaard\ErrorPages\ErrorPagesServiceProvider;

it('does not register the preview routes outside local', function (): void {
    // Default is null → local only, and the test environment is `testing`.
    $this->get('/_error-pages')->assertStatus(404);
})->skip(fn (): bool => config('error-pages.preview.enabled') === true);

describe('with the preview enabled', function (): void {
    beforeEach(function (): void {
        config()->set('error-pages.preview.enabled', true);

        // Routes are registered during boot, so re-boot the provider now that
        // the preview is switched on.
        (new ErrorPagesServiceProvider($this->app))->boot();
    });

    it('lists every previewable status code', function (): void {
        $this->get('/_error-pages')
            ->assertOk()
            ->assertSee('Error pages')
            ->assertSee('404')
            ->assertSee('503');
    });

    it('renders a single status code', function (): void {
        $this->get('/_error-pages/404')
            ->assertOk()
            ->assertSee('We could not find this page');
    });

    it('forces a colour scheme from the query string', function (): void {
        $this->get('/_error-pages/500?theme=dark')
            ->assertOk()
            ->assertSee('color-scheme: dark', false)
            ->assertDontSee('prefers-color-scheme', false);
    });

    it('forces the technical block on and off from the query string', function (): void {
        $this->get('/_error-pages/500?details=1')->assertSee('Technical details');
        $this->get('/_error-pages/500?details=0')->assertDontSee('Technical details');
    });

    it('sets the retry countdown from the query string', function (): void {
        $this->get('/_error-pages/429?retry=300')
            ->assertOk()
            ->assertSee('When to try again')
            ->assertSee('data-ep-countdown', false);
    });

    it('previews the Livewire pop-up', function (): void {
        $this->get('/_error-pages/500?modal=1')
            ->assertOk()
            ->assertSee('ep-modal-root', false)
            ->assertSee('Show pop-up');
    });

    it('moves the pop-up from the query string', function (): void {
        $this->get('/_error-pages/500?modal=1&position=top-left')
            ->assertOk()
            ->assertSee('align-items: flex-start', false);
    });

    it('gives each previewed code a stable message number', function (): void {
        $first = $this->get('/_error-pages/404')->getContent();
        $second = $this->get('/_error-pages/404')->getContent();

        preg_match('/ERR-[0-9A-F]{6}/', $first, $a);
        preg_match('/ERR-[0-9A-F]{6}/', $second, $b);

        expect($a[0] ?? null)->not->toBeNull()->and($a[0])->toBe($b[0] ?? null);
    });
});

it('respects a custom preview path', function (): void {
    config()->set('error-pages.preview.enabled', true);
    config()->set('error-pages.preview.path', '__errors');

    (new ErrorPagesServiceProvider($this->app))->boot();

    $this->get('/__errors')->assertOk();
});

it('renders the preview in the configured theme', function (): void {
    config()->set('error-pages.preview.enabled', true);
    config()->set('error-pages.theme', Theme::Light);

    (new ErrorPagesServiceProvider($this->app))->boot();

    $this->get('/_error-pages/404')
        ->assertOk()
        ->assertSee('color-scheme: light', false);
});
