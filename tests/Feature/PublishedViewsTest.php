<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Exporting the views
|--------------------------------------------------------------------------
|
| `php artisan vendor:publish --tag=janitor-views` copies every blade file
| into resources/views/vendor/janitor. Laravel's namespaced view finder
| checks that directory first, so a published file wins with no extra config.
|
| Three levels of override, each tested below:
|   1. the whole page          — error.blade.php
|   2. one partial             — partials/card.blade.php, styles, actions, …
|   3. one status code         — errors/404.blade.php
|
*/

/**
 * Forget every view Blade has already resolved or compiled.
 *
 * Blade decides whether to recompile by comparing whole-second mtimes, and
 * this whole file publishes to the same paths within a second or two. Without
 * this, a test renders the copy an earlier test compiled — the same reason
 * `php artisan view:clear` belongs after a real `vendor:publish`.
 */
function refreshViews(): void
{
    clearstatcache();
    app('view')->flushFinderCache();

    // Only the compiled templates: the directory itself belongs to the test
    // skeleton, .gitignore and all.
    foreach (File::glob(rtrim((string) config('view.compiled'), '/').'/*.php') as $compiled) {
        File::delete($compiled);
    }
}

function publishViews(): string
{
    test()->artisan('vendor:publish', ['--tag' => 'janitor-views', '--force' => true])->assertSuccessful();
    refreshViews();

    return resource_path('views/vendor/janitor');
}

function writeView(string $path, string $contents): void
{
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    refreshViews();
}

beforeEach(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/missing', fn () => abort(404));
        Route::get('/boom', fn () => throw new RuntimeException('kaboom'));
    });
});

afterEach(function (): void {
    File::deleteDirectory(resource_path('views/vendor/janitor'));
    refreshViews();
});

it('publishes every blade file the package ships', function (): void {
    $directory = publishViews();

    expect(File::exists($directory.'/error.blade.php'))->toBeTrue()
        ->and(File::exists($directory.'/embedded.blade.php'))->toBeTrue()
        ->and(File::exists($directory.'/partials/card.blade.php'))->toBeTrue()
        ->and(File::exists($directory.'/partials/styles.blade.php'))->toBeTrue()
        ->and(File::exists($directory.'/partials/actions.blade.php'))->toBeTrue()
        ->and(File::exists($directory.'/partials/details.blade.php'))->toBeTrue()
        ->and(File::exists($directory.'/partials/meta.blade.php'))->toBeTrue()
        ->and(File::exists($directory.'/partials/retry.blade.php'))->toBeTrue()
        ->and(File::exists($directory.'/partials/livewire-script.blade.php'))->toBeTrue();

    // Same count as the package ships, so nothing is left behind.
    expect(count(File::allFiles($directory)))
        ->toBe(count(File::allFiles(__DIR__.'/../../resources/views')));
});

it('renders identically straight after publishing', function (): void {
    // The request id and the timestamp differ per request by design; everything
    // else must be byte-identical. A published copy that rendered differently
    // would mean the package reads something the published file cannot.
    $normalise = static fn (string $html): string => preg_replace(
        ['/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '/\d{4}-\d{2}-\d{2}T[\d:+]+/', '/\d{1,2} \w{3} \d{4}, \d{2}:\d{2}/'],
        ['UUID', 'TIMESTAMP', 'DATETIME'],
        $html,
    );

    $before = $normalise($this->get('/missing')->getContent());

    publishViews();

    expect($normalise($this->get('/missing')->getContent()))->toBe($before);
});

it('lets a published page replace the whole layout', function (): void {
    $directory = publishViews();

    writeView($directory.'/error.blade.php', <<<'BLADE'
        <!DOCTYPE html>
        <html><body>
            <h1>{{ $error->statusCode }} at {{ $error->branding->name }}</h1>
            <p>{{ $error->message }}</p>
        </body></html>
        BLADE);

    $this->get('/missing')
        ->assertStatus(404)
        ->assertSee('404 at Acme')
        ->assertDontSee('jn-card', false);
});

it('lets a published partial be changed without touching the rest', function (): void {
    $directory = publishViews();

    writeView($directory.'/partials/meta.blade.php', '<div class="my-meta">{{ $error->messageNumber }}</div>');

    $this->get('/missing')
        ->assertStatus(404)
        // The replaced partial is used …
        ->assertSee('my-meta', false)
        ->assertDontSee('Message number')
        // … and everything around it still comes from the package.
        ->assertSee('We could not find this page')
        ->assertSee('What you can do')
        ->assertSee('jn-card', false);
});

it('lets one status code get its own page', function (): void {
    $directory = publishViews();

    writeView($directory.'/errors/404.blade.php', '<h1>Bespoke 404 — {{ $error->title }}</h1>');

    // The per-code view wins for a 404 …
    $this->get('/missing')->assertStatus(404)->assertSee('Bespoke 404 —');

    // … while every other status keeps the shared page.
    $this->get('/boom')->assertStatus(500)->assertSee('What you can do');
});

it('keeps the published styles editable as plain CSS', function (): void {
    $directory = publishViews();

    $styles = File::get($directory.'/partials/styles.blade.php');
    writeView($directory.'/partials/styles.blade.php', $styles."\n<style>.jn-card { border-radius: 0; }</style>");

    $this->get('/missing')->assertSee('.jn-card { border-radius: 0; }', false);
});

it('gives published views the same $error context the package uses', function (): void {
    $directory = publishViews();

    writeView($directory.'/error.blade.php', <<<'BLADE'
        <!DOCTYPE html><html><body>
        status={{ $error->statusCode }}
        title={{ $error->title }}
        number={{ $error->messageNumber }}
        request={{ $error->requestId }}
        brand={{ $error->branding->name }}
        primary={{ $error->palette->light['primary'] }}
        actions={{ count($error->actions()) }}
        locale={{ $error->locale }}
        </body></html>
        BLADE);

    echo "\nRESOLVED: ".app('view')->getFinder()->find('janitor::error')."\n";
    $content = $this->get('/missing')->getContent();

    expect($content)->toContain('status=404')
        ->and($content)->toContain('title=We could not find this page')
        ->and($content)->toContain('number=ERR-')
        ->and($content)->toContain('brand=Acme')
        ->and($content)->toContain('primary=#')
        ->and($content)->toContain('actions=2')
        ->and($content)->toContain('locale=en');
});
