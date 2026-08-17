<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Support;

/**
 * Scheme filter for anything that ends up in an `href` or an `src`.
 *
 * Blade escaping makes a URL safe as *markup*; it does nothing about what the
 * URL does when followed. `javascript:` survives htmlspecialchars() intact and
 * runs on click. These URLs come from config, from a custom BrandingResolver
 * and from whichever Filament panel happens to be active, so filtering them
 * here means neither the rendered page nor the Livewire payload can carry a
 * link that executes.
 */
final class Url
{
    /**
     * Schemes a browser treats as code rather than as a location.
     */
    private const EXECUTABLE_SCHEMES = ['javascript', 'vbscript'];

    /**
     * `data:` renders inline images fine but also renders inline HTML, so it is
     * allowed for assets and refused for links.
     */
    private const DOCUMENT_SCHEMES = ['data', 'file', 'blob'];

    /**
     * A URL safe to put in an `href`, or null when it is not.
     */
    public static function link(?string $url): ?string
    {
        return self::filter($url, [...self::EXECUTABLE_SCHEMES, ...self::DOCUMENT_SCHEMES]);
    }

    /**
     * A URL safe to put in an `src`, where a data: image is legitimate.
     */
    public static function asset(?string $url): ?string
    {
        return self::filter($url, self::EXECUTABLE_SCHEMES);
    }

    /**
     * @param  list<string>  $refused
     */
    private static function filter(?string $url, array $refused): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // Browsers strip control characters and whitespace before resolving the
        // scheme, so "java\tscript:alert(1)" is a javascript: URL and
        // "  JavaScript:…" is too. Probe against the same normalisation.
        $probe = strtolower((string) preg_replace('/[\x00-\x20\x7f]/', '', $url));

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $probe, $matches) !== 1) {
            // No scheme at all: a relative path, a fragment or a query.
            return $url;
        }

        return in_array($matches[1], $refused, true) ? null : $url;
    }
}
