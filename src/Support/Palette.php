<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Support;

use FreshwaveOnline\Janitor\Enums\Theme;

/**
 * Turns the configured primary colour into the full set of CSS custom
 * properties the error pages use, for both the light and the dark surface.
 *
 * Greys are fixed and deliberately neutral; only the accent is themable, which
 * is what keeps the pages white-label by default.
 */
final class Palette
{
    /**
     * Neutral base tokens. Everything else is derived from the primary colour.
     *
     * @var array<string, array<string, string>>
     */
    private const NEUTRALS = [
        'light' => [
            'bg' => '#f4f4f5',
            'bg-accent' => '#ffffff',
            'surface' => '#ffffff',
            'surface-muted' => '#fafafa',
            'surface-sunken' => '#f4f4f5',
            'border' => '#e4e4e7',
            'border-strong' => '#d4d4d8',
            'text' => '#18181b',
            'text-muted' => '#52525b',
            'text-subtle' => '#8b8b93',
            'shadow' => '0 1px 2px rgba(9, 9, 11, .04), 0 12px 32px -12px rgba(9, 9, 11, .12)',
            'shadow-sm' => '0 1px 2px rgba(9, 9, 11, .06)',
            'code-bg' => '#fafafa',
        ],
        'dark' => [
            'bg' => '#09090b',
            'bg-accent' => '#101013',
            'surface' => '#141417',
            'surface-muted' => '#1a1a1e',
            'surface-sunken' => '#101013',
            'border' => '#27272a',
            'border-strong' => '#3f3f46',
            'text' => '#f4f4f5',
            'text-muted' => '#a1a1aa',
            'text-subtle' => '#71717a',
            'shadow' => '0 1px 2px rgba(0, 0, 0, .4), 0 12px 32px -12px rgba(0, 0, 0, .7)',
            'shadow-sm' => '0 1px 2px rgba(0, 0, 0, .5)',
            'code-bg' => '#101013',
        ],
    ];

    private const DEFAULT_PRIMARY = '#4f46e5';

    /**
     * @param  array<string, string>  $light
     * @param  array<string, string>  $dark
     */
    private function __construct(
        public readonly array $light,
        public readonly array $dark,
        public readonly Theme $theme,
    ) {}

    /**
     * @param  array{primary?: string|null, light?: string|null, dark?: string|null, auto_contrast?: bool}  $colors
     */
    public static function fromConfig(array $colors, Theme $theme = Theme::Auto): self
    {
        $base = Color::parse($colors['primary'] ?? null) ?? Color::parse(self::DEFAULT_PRIMARY);
        assert($base instanceof Color);

        $lightPrimary = Color::parse($colors['light'] ?? null) ?? $base;
        $darkPrimary = Color::parse($colors['dark'] ?? null) ?? $base;

        $autoContrast = (bool) ($colors['auto_contrast'] ?? true);

        return new self(
            self::tokens($lightPrimary, 'light', $autoContrast),
            self::tokens($darkPrimary, 'dark', $autoContrast),
            $theme,
        );
    }

    /**
     * @return array<string, string>
     */
    public function forScheme(string $scheme): array
    {
        return $scheme === 'dark' ? $this->dark : $this->light;
    }

    /**
     * Renders the custom properties for one scheme as a CSS declaration block body.
     */
    public function declarations(string $scheme, string $indent = '    '): string
    {
        $lines = [];

        foreach ($this->forScheme($scheme) as $token => $value) {
            $lines[] = $indent.'--jn-'.$token.': '.$value.';';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private static function tokens(Color $primary, string $scheme, bool $autoContrast): array
    {
        $neutrals = self::NEUTRALS[$scheme];

        $background = Color::parse($neutrals['surface']);
        assert($background instanceof Color);

        // Guarantee the accent stays visible on its own surface (WCAG 3:1 for UI).
        $accent = $autoContrast ? $primary->ensureContrast($background, 3.0) : $primary;

        $solid = $scheme === 'dark' ? $accent : $accent->ensureContrast($background, 3.2);
        $hover = $scheme === 'dark' ? $solid->lighten(0.12) : $solid->darken(0.12);
        $active = $scheme === 'dark' ? $solid->lighten(0.2) : $solid->darken(0.2);

        $tokens = $neutrals;
        $tokens['primary'] = $solid->toHex();
        $tokens['primary-hover'] = $hover->toHex();
        $tokens['primary-active'] = $active->toHex();
        $tokens['primary-contrast'] = $solid->readableTextColor()->toHex();
        $tokens['primary-channels'] = $solid->toChannels();
        $tokens['primary-soft'] = $solid->toRgba($scheme === 'dark' ? 0.16 : 0.1);
        $tokens['primary-soft-hover'] = $solid->toRgba($scheme === 'dark' ? 0.24 : 0.16);
        $tokens['primary-border'] = $solid->toRgba($scheme === 'dark' ? 0.36 : 0.28);
        $tokens['primary-ring'] = $solid->toRgba(0.4);
        // A tinted accent that still reads as text on the surface (AA, 4.5:1).
        $tokens['primary-text'] = $accent->ensureContrast($background, 4.5)->toHex();

        return $tokens;
    }
}
