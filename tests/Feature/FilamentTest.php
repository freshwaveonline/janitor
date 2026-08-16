<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Integrations\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Filament is a suggestion, never a requirement. These tests run without it
 * installed — which is exactly the situation that must not break.
 */
beforeEach(function (): void {
    Route::middleware('web')->get('/missing', fn () => throw new NotFoundHttpException);
});

it('reports Filament as absent when it is not installed', function (): void {
    expect(Filament::installed())->toBeFalse();
});

it('returns nothing rather than throwing when Filament is absent', function (): void {
    $request = Request::create('/admin/users');

    expect(Filament::panel($request))->toBeNull()
        ->and(Filament::brandName($request))->toBeNull()
        ->and(Filament::brandLogo($request))->toBeNull()
        ->and(Filament::primaryColor($request))->toBeNull()
        ->and(Filament::homeUrl($request))->toBeNull()
        ->and(Filament::loginUrl($request))->toBeNull()
        ->and(Filament::hasDarkMode($request))->toBeNull()
        ->and(Filament::isPanelRequest($request))->toBeFalse();
});

it('renders a normal page in an app without Filament', function (): void {
    config()->set('janitor.filament.enabled', true);

    $this->get('/missing')
        ->assertStatus(404)
        ->assertSee('Acme')
        ->assertSee('We could not find this page');
});

it('does not consult Filament at all when the integration is disabled', function (): void {
    // Pretend Filament is installed; with the integration off nothing may call
    // into it, so the page must still render from this package's own config.
    Filament::fake(true);

    config()->set('janitor.filament.enabled', false);
    config()->set('janitor.brand.name', 'Acme Portal');

    $this->get('/missing')->assertStatus(404)->assertSee('Acme Portal');
});

it('keeps explicit config ahead of anything Filament could supply', function (): void {
    Filament::fake(true);

    config()->set('janitor.filament.enabled', true);
    config()->set('janitor.filament.only_on_panel_routes', false);
    config()->set('janitor.brand.name', 'Explicit Name');
    config()->set('janitor.colors.primary', '#b91c1c');

    $this->get('/missing')
        ->assertSee('Explicit Name')
        ->assertSee('--jn-primary: #b91c1c', false);
});

it('survives a Filament API that throws', function (): void {
    // A Filament upgrade renaming a method must never turn a 404 into a 500.
    Filament::fake(true);

    config()->set('janitor.filament.enabled', true);
    config()->set('janitor.filament.only_on_panel_routes', false);

    $this->get('/missing')->assertStatus(404);
});
