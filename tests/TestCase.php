<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Tests;

use FreshwaveOnline\Janitor\Integrations\Filament;
use FreshwaveOnline\Janitor\JanitorServiceProvider;
use FreshwaveOnline\Janitor\Support\Icons;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Both keep static state; reset so one test cannot leak into the next.
        // Filament is pinned to "absent" rather than left to class_exists():
        // tests/Pest.php can make the stand-in classes loadable, and whether
        // some earlier test happened to load them must not decide what the
        // rest of the suite sees. FilamentBrandingTest opts in explicitly.
        Filament::fake(false);
        Icons::flush();
    }

    protected function tearDown(): void
    {
        Filament::fake(false);
        Icons::flush();

        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [JanitorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Acme');
        $app['config']->set('app.debug', false);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // The package defers to Ignition while debugging; the suite exercises
        // the package's own pages, so take over unconditionally.
        $app['config']->set('janitor.details.replace_debug_page', true);
    }
}
