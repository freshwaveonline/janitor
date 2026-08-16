<?php

declare(strict_types=1);

use Vvdboogaard\ErrorPages\Enums\Theme;
use Vvdboogaard\ErrorPages\Support\Color;
use Vvdboogaard\ErrorPages\Support\Palette;

it('parses every colour notation a config might contain', function (string $input, string $expected): void {
    expect(Color::parse($input)?->toHex())->toBe($expected);
})->with([
    ['#4f46e5', '#4f46e5'],
    ['4f46e5', '#4f46e5'],
    ['#FFF', '#ffffff'],
    ['#f00', '#ff0000'],
    ['rgb(79, 70, 229)', '#4f46e5'],
    ['rgb(79 70 229)', '#4f46e5'],
    ['79, 70, 229', '#4f46e5'],   // Filament v3 shade format
    ['  #4F46E5  ', '#4f46e5'],
]);

it('returns null for values it cannot parse', function (?string $input): void {
    expect(Color::parse($input))->toBeNull();
})->with([null, '', 'not-a-colour', '#12345', 'hsl(240, 80%, 60%)']);

it('picks readable text on top of a colour', function (): void {
    expect(Color::parse('#ffffff')?->readableTextColor()->toHex())->toBe('#17171b')
        ->and(Color::parse('#111827')?->readableTextColor()->toHex())->toBe('#ffffff');
});

it('computes contrast ratios symmetrically', function (): void {
    $white = Color::parse('#ffffff');
    $black = Color::parse('#000000');

    expect($white->contrastRatio($black))->toBe($black->contrastRatio($white))
        ->and(round($white->contrastRatio($black), 1))->toBe(21.0);
});

it('lightens a dark accent until it is readable on a dark surface', function (): void {
    $navy = Color::parse('#0b1c3f');
    $darkSurface = Color::parse('#141417');

    expect($navy->contrastRatio($darkSurface))->toBeLessThan(3.0);

    $adjusted = $navy->ensureContrast($darkSurface, 3.0);

    expect($adjusted->contrastRatio($darkSurface))->toBeGreaterThanOrEqual(3.0);
});

it('leaves a colour alone when it already has enough contrast', function (): void {
    $indigo = Color::parse('#4f46e5');
    $white = Color::parse('#ffffff');

    expect($indigo->ensureContrast($white, 3.0)->toHex())->toBe('#4f46e5');
});

it('builds light and dark tokens from a single primary colour', function (): void {
    $palette = Palette::fromConfig(['primary' => '#4f46e5']);

    expect($palette->light)->toHaveKeys(['primary', 'primary-hover', 'primary-contrast', 'text', 'surface'])
        ->and($palette->dark['surface'])->not->toBe($palette->light['surface'])
        ->and($palette->light['primary-contrast'])->toBe('#ffffff');
});

it('lets the light and dark overrides replace the primary colour', function (): void {
    $palette = Palette::fromConfig([
        'primary' => '#4f46e5',
        'light' => '#b91c1c',
        'dark' => '#22c55e',
        'auto_contrast' => false,
    ]);

    expect($palette->light['primary'])->toBe('#b91c1c')
        ->and($palette->dark['primary'])->toBe('#22c55e');
});

it('falls back to the default primary when the configured value is unusable', function (): void {
    $palette = Palette::fromConfig(['primary' => 'chartreuse-ish']);

    expect($palette->light['primary'])->toStartWith('#');
});

it('renders custom properties as CSS declarations', function (): void {
    $css = Palette::fromConfig(['primary' => '#4f46e5'], Theme::Auto)->declarations('light');

    expect($css)->toContain('--ep-primary:')
        ->and($css)->toContain('--ep-text:')
        ->and($css)->toEndWith(';');
});

it('keeps a low-contrast brand colour usable on white', function (): void {
    // A pale yellow brand colour would be invisible as a button; auto-contrast
    // darkens it rather than shipping an unreadable page.
    $palette = Palette::fromConfig(['primary' => '#fde047']);

    $primary = Color::parse($palette->light['primary']);
    $surface = Color::parse($palette->light['surface']);

    expect($primary->contrastRatio($surface))->toBeGreaterThanOrEqual(3.0);
});
