<?php

declare(strict_types=1);

use Filament\Facades\Filament as FilamentFacade;
use Filament\Panel;
use FreshwaveOnline\Janitor\Integrations\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Inheriting a panel's branding
|--------------------------------------------------------------------------
|
| With Filament installed, an error inside /admin should still look like the
| panel. Everything here runs against the stand-in described in Pest.php, so
| the bridge is exercised without making Filament a dependency.
|
*/

beforeEach(function (): void {
    config()->set('janitor.views.prefer_application_views', false);
    config()->set('janitor.filament.enabled', true);

    Filament::fake(true);
    FilamentFacade::reset();

    Route::middleware('web')->get('/admin/users', fn () => throw new NotFoundHttpException);
    Route::middleware('web')->get('/shop', fn () => throw new NotFoundHttpException);
});

afterEach(function (): void {
    FilamentFacade::reset();
});

function panel(mixed ...$overrides): Panel
{
    return new Panel(...[
        'path' => 'admin',
        'brandName' => 'Acme Admin',
        'brandLogo' => '/img/panel.svg',
        'colors' => ['primary' => [600 => '#b91c1c']],
        'url' => 'https://admin.example.test',
        'hasLogin' => true,
        'loginUrl' => 'https://admin.example.test/login',
        ...$overrides,
    ]);
}

it('inherits the brand name from the active panel', function (): void {
    FilamentFacade::stub([panel()]);

    $this->get('/admin/users')->assertStatus(404)->assertSee('Acme Admin');
});

it('inherits the primary colour from the panel palette', function (): void {
    FilamentFacade::stub([panel()]);

    $this->get('/admin/users')->assertSee('--jn-primary: #b91c1c', false);
});

it('reads the configured shade out of the panel palette', function (): void {
    config()->set('janitor.filament.color_shade', 500);

    FilamentFacade::stub([panel(colors: ['primary' => [500 => '#15803d', 600 => '#b91c1c']])]);

    $this->get('/admin/users')->assertSee('--jn-primary: #15803d', false);
});

it('accepts a panel palette given as a single colour', function (): void {
    FilamentFacade::stub([panel(colors: ['primary' => '#7c3aed'])]);

    expect(Filament::primaryColor(Request::create('/admin/users')))->toBe('#7c3aed');
});

it('inherits the panel logo', function (): void {
    FilamentFacade::stub([panel()]);

    $this->get('/admin/users')->assertSee('/img/panel.svg', false);
});

it('ignores a panel logo that is not a plain URL', function (mixed $logo): void {
    FilamentFacade::stub([panel(brandLogo: $logo)]);

    expect(Filament::brandLogo(Request::create('/admin/users')))->toBeNull();
})->with([
    'a view name' => ['filament.logo'],
    'markup' => ['<svg onload=alert(1)></svg>'],
    'an executable URL' => ['javascript:alert(1)'],
    'not a string at all' => [null],
]);

it('inherits the panel home and login URLs', function (): void {
    FilamentFacade::stub([panel()]);

    $request = Request::create('/admin/users');

    expect(Filament::homeUrl($request))->toBe('https://admin.example.test')
        ->and(Filament::loginUrl($request))->toBe('https://admin.example.test/login');
});

it('offers no login URL for a panel without login', function (): void {
    FilamentFacade::stub([panel(hasLogin: false)]);

    expect(Filament::loginUrl(Request::create('/admin/users')))->toBeNull();
});

it('only inherits on panel routes by default', function (): void {
    config()->set('janitor.filament.only_on_panel_routes', true);
    config()->set('app.name', 'Acme Shop');

    FilamentFacade::stub([panel()]);

    $this->get('/admin/users')->assertSee('Acme Admin');
    $this->get('/shop')->assertSee('Acme Shop')->assertDontSee('Acme Admin');
});

it('inherits everywhere once panel-only is turned off', function (): void {
    config()->set('janitor.filament.only_on_panel_routes', false);

    FilamentFacade::stub([panel()]);

    $this->get('/shop')->assertSee('Acme Admin');
});

it('lets explicit config override anything the panel reports', function (): void {
    config()->set('janitor.brand.name', 'Explicit');
    config()->set('janitor.colors.primary', '#0ea5e9');
    config()->set('janitor.links.home', 'https://explicit.example.test');

    FilamentFacade::stub([panel()]);

    $this->get('/admin/users')
        ->assertSee('Explicit')
        ->assertDontSee('Acme Admin')
        ->assertSee('--jn-primary: #0ea5e9', false)
        ->assertSee('https://explicit.example.test', false);
});

it('honours each inherit toggle on its own', function (string $feature): void {
    config()->set('janitor.filament.only_on_panel_routes', false);
    config()->set('janitor.filament.inherit.'.$feature, false);
    config()->set('app.name', 'Acme Shop');

    FilamentFacade::stub([panel()]);

    $content = $this->get('/admin/users')->getContent();

    match ($feature) {
        'brand_name' => expect($content)->toContain('Acme Shop')->not->toContain('Acme Admin'),
        'brand_logo' => expect($content)->not->toContain('/img/panel.svg'),
        'primary_color' => expect($content)->not->toContain('--jn-primary: #b91c1c'),
        default => expect($content)->toBeString(),
    };
})->with(['brand_name', 'brand_logo', 'primary_color', 'home_url', 'login_url']);

it('matches the panel that owns the request path', function (): void {
    FilamentFacade::stub([
        panel(path: 'app', brandName: 'Customer App'),
        panel(path: 'admin', brandName: 'Acme Admin'),
    ]);

    $this->get('/admin/users')->assertSee('Acme Admin')->assertDontSee('Customer App');
});

it('prefers the panel Filament reports as current', function (): void {
    FilamentFacade::stub(
        [panel(path: 'admin', brandName: 'By path')],
        current: panel(path: 'admin', brandName: 'Current panel'),
    );

    $this->get('/admin/users')->assertSee('Current panel');
});

it('falls back to the default panel outside every panel path', function (): void {
    config()->set('janitor.filament.only_on_panel_routes', false);

    FilamentFacade::stub([panel(path: 'admin', brandName: 'Default panel')]);

    expect(Filament::brandName(Request::create('/shop')))->toBe('Default panel');
});

it('renders a page rather than an exception when the Filament API changes', function (): void {
    // The scenario this bridge exists to survive: an upgrade renames a method.
    config()->set('janitor.filament.only_on_panel_routes', false);

    FilamentFacade::breakIt();

    $this->get('/admin/users')
        ->assertStatus(404)
        ->assertSee('We could not find this page')
        // With nothing readable from the panel, the app name still brands it.
        ->assertSee('Acme');
});

it('reads a panel that answers with an unexpected shape', function (mixed $name): void {
    config()->set('janitor.filament.only_on_panel_routes', false);

    FilamentFacade::stub([panel(brandName: $name)]);

    $this->get('/admin/users')->assertStatus(404)->assertSee('Acme');
})->with([
    'an object' => [new stdClass],
    'an array' => [['not', 'a', 'name']],
    'an empty string' => [''],
    'null' => [null],
]);

it('detects the Filament install through class_exists when nothing is faked', function (): void {
    Filament::fake(null);

    // The stand-in makes the facade loadable, which is exactly what the real
    // detection looks for.
    expect(Filament::installed())->toBeTrue();
});
