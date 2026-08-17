<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Stand-ins for the optional integrations
|--------------------------------------------------------------------------
|
| Filament is a suggestion, never a dev dependency, so `Filament\Facades\
| Filament` does not exist in this suite — which is the situation most of
| these tests are about. FilamentBrandingTest needs the opposite situation,
| so the classes under tests/Fixtures/filament stand in for it.
|
| Registered here rather than in composer.json's autoload-dev: appended to
| the chain, this autoloader only ever runs after Composer's, so a real
| Filament installation always wins. TestCase then pins the integration to
| "absent" for every test, and the tests that want a panel opt in, which
| keeps the rest of the suite independent of whether these files were ever
| loaded.
|
*/
spl_autoload_register(static function (string $class): void {
    if (! str_starts_with($class, 'Filament\\')) {
        return;
    }

    $path = __DIR__.'/Fixtures/filament/'.str_replace('\\', '/', substr($class, strlen('Filament\\'))).'.php';

    if (is_file($path)) {
        require_once $path;
    }
});
