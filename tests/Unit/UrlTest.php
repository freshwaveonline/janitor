<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Support\Url;

/*
|--------------------------------------------------------------------------
| URL scheme filtering
|--------------------------------------------------------------------------
|
| Blade escaping makes a URL safe as markup and does nothing about what it
| does when followed. These URLs come from config, from a custom
| BrandingResolver and from whichever Filament panel is active.
|
*/

it('keeps a URL that only ever navigates', function (string $url): void {
    expect(Url::link($url))->toBe($url);
})->with([
    'absolute' => ['https://example.test/help'],
    'insecure but honest' => ['http://example.test'],
    'protocol relative' => ['//cdn.example.test/logo.svg'],
    'root relative' => ['/dashboard'],
    'relative' => ['dashboard/settings'],
    'query only' => ['?retry=1'],
    'fragment' => ['#main'],
    'mailto' => ['mailto:help@example.test?subject=Error'],
    'telephone' => ['tel:+3110000000'],
]);

it('refuses a URL that executes', function (string $url): void {
    expect(Url::link($url))->toBeNull();
})->with([
    'javascript' => ['javascript:alert(1)'],
    'uppercase' => ['JAVASCRIPT:alert(1)'],
    'mixed case' => ['JaVaScRiPt:alert(1)'],
    'vbscript' => ['vbscript:msgbox(1)'],
    'leading space' => ['   javascript:alert(1)'],
    // Browsers strip these before resolving the scheme, so we must too.
    'embedded tab' => ["java\tscript:alert(1)"],
    'embedded newline' => ["java\nscript:alert(1)"],
    'embedded carriage return' => ["java\rscript:alert(1)"],
    'null byte' => ["java\0script:alert(1)"],
    'inline document' => ['data:text/html,<script>alert(1)</script>'],
    'local file' => ['file:///etc/passwd'],
    'blob' => ['blob:https://example.test/uuid'],
]);

it('allows a data: image as an asset but never as a link', function (): void {
    $logo = 'data:image/svg+xml;base64,PHN2Zy8+';

    expect(Url::asset($logo))->toBe($logo)
        ->and(Url::link($logo))->toBeNull();
});

it('refuses an executable URL as an asset too', function (): void {
    expect(Url::asset('javascript:alert(1)'))->toBeNull();
});

it('treats nothing as nothing', function (?string $url): void {
    expect(Url::link($url))->toBeNull()
        ->and(Url::asset($url))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ["  \t\n "],
]);

it('trims a usable URL rather than rejecting it', function (): void {
    expect(Url::link('  https://example.test  '))->toBe('https://example.test');
});

it('leaves an unfamiliar but harmless scheme alone', function (string $url): void {
    // An allowlist would break deep links into real applications; only the
    // schemes a browser treats as code are refused.
    expect(Url::link($url))->toBe($url);
})->with([
    'app deep link' => ['acme-app://orders/42'],
    'ftp' => ['ftp://files.example.test'],
    'a path with a colon in it' => ['/reports/2026:q1'],
]);
