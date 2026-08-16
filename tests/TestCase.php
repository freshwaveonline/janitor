<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vvdboogaard\ErrorPages\ErrorPagesServiceProvider;
use Vvdboogaard\ErrorPages\Integrations\Filament;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Filament memoises its own absence; reset so one test's fake cannot
        // leak into the next.
        Filament::fake(null);
    }

    protected function tearDown(): void
    {
        Filament::fake(null);

        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ErrorPagesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Acme');
        $app['config']->set('app.debug', false);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // The package defers to Ignition while debugging; the suite exercises
        // the package's own pages, so take over unconditionally.
        $app['config']->set('error-pages.details.replace_debug_page', true);
    }
}
