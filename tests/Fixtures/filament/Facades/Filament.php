<?php

declare(strict_types=1);

namespace Filament\Facades;

use Filament\Panel;
use RuntimeException;

/**
 * Stand-in for Filament's facade.
 *
 * Filament is a suggestion, never a dev dependency, so there is no real class
 * to inherit branding from in this suite. tests/Pest.php registers an
 * autoloader for this namespace *after* Composer's, so a real installation
 * always wins.
 */
final class Filament
{
    private static ?Panel $current = null;

    /** @var list<Panel> */
    private static array $panels = [];

    private static ?Panel $default = null;

    private static bool $broken = false;

    /**
     * @param  list<Panel>  $panels
     */
    public static function stub(array $panels = [], ?Panel $current = null, ?Panel $default = null): void
    {
        self::$panels = $panels;
        self::$current = $current;
        self::$default = $default ?? ($panels[0] ?? null);
        self::$broken = false;
    }

    /**
     * Simulate a Filament upgrade that renamed or removed what we call.
     */
    public static function breakIt(): void
    {
        self::reset();
        self::$broken = true;
    }

    public static function reset(): void
    {
        self::$panels = [];
        self::$current = null;
        self::$default = null;
        self::$broken = false;
    }

    public static function getCurrentPanel(): ?Panel
    {
        self::guard();

        return self::$current;
    }

    /**
     * @return list<Panel>
     */
    public static function getPanels(): array
    {
        self::guard();

        return self::$panels;
    }

    public static function getDefaultPanel(): ?Panel
    {
        self::guard();

        return self::$default;
    }

    private static function guard(): void
    {
        if (self::$broken) {
            throw new RuntimeException('Call to undefined method Filament::getPanels()');
        }
    }
}
