<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

afterEach(function (): void {
    File::delete(config_path('error-pages.php'));
    File::deleteDirectory(resource_path('views/vendor/error-pages'));
    File::deleteDirectory(lang_path('vendor/error-pages'));
});

it('publishes everything with --all', function (): void {
    $this->artisan('error-pages:install', ['--all' => true])->assertSuccessful();

    expect(File::exists(config_path('error-pages.php')))->toBeTrue()
        ->and(File::exists(resource_path('views/vendor/error-pages/error.blade.php')))->toBeTrue()
        ->and(File::exists(lang_path('vendor/error-pages/en/errors.php')))->toBeTrue()
        ->and(File::exists(lang_path('vendor/error-pages/nl/errors.php')))->toBeTrue();
});

it('publishes only the config when run non-interactively', function (): void {
    $this->artisan('error-pages:install')->assertSuccessful();

    expect(File::exists(config_path('error-pages.php')))->toBeTrue()
        ->and(File::exists(resource_path('views/vendor/error-pages/error.blade.php')))->toBeFalse();
});

it('publishes a config file that is valid PHP and matches the packaged one', function (): void {
    $this->artisan('error-pages:install', ['--all' => true])->assertSuccessful();

    $published = require config_path('error-pages.php');

    expect($published)->toBeArray()
        ->and($published)->toHaveKeys(['enabled', 'colors', 'message_number', 'livewire', 'filament', 'links'])
        ->and($published)->toEqual(require __DIR__.'/../../config/error-pages.php');
});

it('lets a published view override the packaged one', function (): void {
    $this->artisan('error-pages:install', ['--all' => true])->assertSuccessful();

    File::put(resource_path('views/vendor/error-pages/error.blade.php'), 'Published page for {{ $error->statusCode }}.');
    $this->app['view']->flushFinderCache();

    Route::middleware('web')
        ->get('/missing', fn () => abort(404));

    $this->get('/missing')->assertStatus(404)->assertSee('Published page for 404.');
});
