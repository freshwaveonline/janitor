<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Integrations;

use FreshwaveOnline\Janitor\Support\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Optional Filament bridge.
 *
 * The package must work in a plain Laravel app, so nothing here may be imported
 * at the top of a class that is always loaded, and every call is wrapped: a
 * Filament upgrade that renames a method should never turn a 404 page into a 500.
 *
 * When Filament *is* installed we borrow the panel's brand name, logo and
 * primary colour so an error inside `/admin` still looks like the panel.
 */
final class Filament
{
    private static ?bool $installed = null;

    public static function installed(): bool
    {
        return self::$installed ??= class_exists(\Filament\Facades\Filament::class);
    }

    /**
     * Only used by the test-suite to simulate an absent Filament install.
     */
    public static function fake(?bool $installed): void
    {
        self::$installed = $installed;
    }

    /**
     * Resolve the panel that owns the current request, falling back to the
     * default panel. During an exception Filament's "current panel" may never
     * have been set, so path matching is the reliable route.
     */
    public static function panel(?Request $request = null): mixed
    {
        if (! self::installed()) {
            return null;
        }

        return self::guard(static function () use ($request): mixed {
            $facade = \Filament\Facades\Filament::class;

            $current = $facade::getCurrentPanel();

            if ($current !== null) {
                return $current;
            }

            if ($request !== null) {
                $path = trim($request->path(), '/');

                foreach (self::panels() as $panel) {
                    $prefix = self::panelPath($panel);

                    if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                        return $panel;
                    }
                }
            }

            return $facade::getDefaultPanel();
        });
    }

    /**
     * True when the failing request was aimed at a Filament panel.
     */
    public static function isPanelRequest(Request $request): bool
    {
        if (! self::installed()) {
            return false;
        }

        return self::guard(static function () use ($request): bool {
            $path = trim($request->path(), '/');

            foreach (self::panels() as $panel) {
                $prefix = self::panelPath($panel);

                if ($prefix === '') {
                    continue;
                }

                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return true;
                }
            }

            return false;
        }) ?? false;
    }

    public static function brandName(?Request $request = null): ?string
    {
        $panel = self::panel($request);

        if ($panel === null) {
            return null;
        }

        return self::guard(static function () use ($panel): ?string {
            $name = $panel->getBrandName();

            if ($name instanceof Htmlable) {
                $name = strip_tags($name->toHtml());
            }

            $name = is_string($name) ? trim($name) : null;

            return $name === '' ? null : $name;
        });
    }

    public static function brandLogo(?Request $request = null): ?string
    {
        $panel = self::panel($request);

        if ($panel === null) {
            return null;
        }

        return self::guard(static function () use ($panel): ?string {
            $logo = $panel->getBrandLogo();

            // Only a plain URL is usable; Filament also allows Htmlable/view names
            // which we cannot safely inline into a standalone error page.
            return is_string($logo) && preg_match('#^(https?:)?//|^/#', $logo) === 1 ? $logo : null;
        });
    }

    public static function homeUrl(?Request $request = null): ?string
    {
        $panel = self::panel($request);

        if ($panel === null) {
            return null;
        }

        return self::guard(static function () use ($panel): ?string {
            $url = $panel->getUrl();

            return is_string($url) && $url !== '' ? $url : null;
        });
    }

    public static function loginUrl(?Request $request = null): ?string
    {
        $panel = self::panel($request);

        if ($panel === null) {
            return null;
        }

        return self::guard(static function () use ($panel): ?string {
            if (! $panel->hasLogin()) {
                return null;
            }

            $url = $panel->getLoginUrl();

            return is_string($url) && $url !== '' ? $url : null;
        });
    }

    /**
     * The panel's primary colour, as a hex string.
     *
     * Filament stores colours as a shade map (50…950). Shade 600 is what the
     * panel uses for solid buttons in light mode, which is exactly what we want.
     */
    public static function primaryColor(?Request $request = null, int $shade = 600): ?string
    {
        $panel = self::panel($request);

        if ($panel === null) {
            return null;
        }

        return self::guard(static function () use ($panel, $shade): ?string {
            $colors = $panel->getColors();

            if (! is_array($colors)) {
                return null;
            }

            $primary = $colors['primary'] ?? null;

            if (is_string($primary)) {
                return Color::parse($primary)?->toHex();
            }

            if (! is_array($primary)) {
                return null;
            }

            // Shades may be keyed as int or string; prefer the requested one and
            // walk outwards so an unusual palette still resolves to something.
            foreach ([$shade, 500, 600, 700, 400] as $candidate) {
                $value = $primary[$candidate] ?? $primary[(string) $candidate] ?? null;

                if (is_string($value) && ($color = Color::parse($value)) !== null) {
                    return $color->toHex();
                }
            }

            return null;
        });
    }

    /**
     * Whether the panel offers a dark-mode switch. Used only as a hint: if the
     * panel is light-only, forcing `auto` on the error page would be jarring.
     */
    public static function hasDarkMode(?Request $request = null): ?bool
    {
        $panel = self::panel($request);

        if ($panel === null) {
            return null;
        }

        return self::guard(static fn (): bool => (bool) $panel->hasDarkMode());
    }

    /**
     * Every registered panel, or an empty list when Filament hands back
     * something we cannot walk.
     *
     * @return iterable<mixed>
     */
    private static function panels(): iterable
    {
        $panels = \Filament\Facades\Filament::getPanels();

        return is_iterable($panels) ? $panels : [];
    }

    /**
     * A panel's URL prefix, trimmed of slashes. Filament types this as a
     * string, but a panel object from a future version may not.
     */
    private static function panelPath(mixed $panel): string
    {
        if (! is_object($panel) || ! method_exists($panel, 'getPath')) {
            return '';
        }

        $path = $panel->getPath();

        return is_string($path) ? trim($path, '/') : '';
    }

    /**
     * Run a Filament lookup, swallowing anything it throws.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn|null
     */
    private static function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
