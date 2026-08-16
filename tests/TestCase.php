<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vvdboogaard\ErrorPages\ErrorPagesServiceProvider;
use Vvdboogaard\ErrorPages\Integrations\Filament;
use Vvdboogaard\ErrorPages\Support\Icons;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Both keep static state; reset so one test cannot leak into the next.
        Filament::fake(null);
        Icons::flush();
    }

    protected function tearDown(): void
    {
        Filament::fake(null);
        Icons::flush();

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
