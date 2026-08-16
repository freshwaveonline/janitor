<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Enums\MessageNumberAlphabet;
use FreshwaveOnline\Janitor\Enums\OriginStrategy;
use FreshwaveOnline\Janitor\Support\MessageNumber;

/**
 * The whole promise of the message number is that it is stable. These tests are
 * the contract: same place → same number, different place → different number.
 */
function messageNumber(array $overrides = [], string $basePath = '/var/www/app'): MessageNumber
{
    return new MessageNumber(array_merge([
        'enabled' => true,
        'prefix' => 'ERR',
        'separator' => '-',
        'length' => 6,
        'alphabet' => MessageNumberAlphabet::Hex,
        'algorithm' => 'crc32b',
        'origin' => OriginStrategy::Thrown,
    ], $overrides), $basePath);
}

it('gives the same number for the same file and line', function (): void {
    $generator = messageNumber();

    // Two separate exceptions constructed on the very same line.
    $makeException = fn (): RuntimeException => new RuntimeException('whatever');

    $first = $generator->for($makeException(), 500);
    $second = $generator->for($makeException(), 500);

    expect($first)->toBe($second)->and($first)->toStartWith('ERR-');
});

it('gives a different number for a different line in the same file', function (): void {
    $generator = messageNumber();

    $a = new RuntimeException('one');
    $b = new RuntimeException('two');

    expect($generator->for($a, 500))->not->toBe($generator->for($b, 500));
});

it('ignores the exception message', function (): void {
    $generator = messageNumber();

    $make = fn (string $message): RuntimeException => new RuntimeException($message);

    expect($generator->for($make('first'), 500))->toBe($generator->for($make('second'), 500));
});

it('stays stable across deploy directories', function (): void {
    // The same file, deployed twice under different release paths, must
    // fingerprint identically — otherwise every deploy invalidates every
    // message number ever quoted in a ticket.
    $one = messageNumber(basePath: '/var/www/releases/20240101');
    $two = messageNumber(basePath: '/var/www/releases/20250716');

    expect($one->fingerprint(new RuntimeException, 500))
        ->not->toContain('releases');

    expect(str_contains($one->fingerprint(new RuntimeException, 500), '/var/www'))->toBeFalse();
    expect(str_contains($two->fingerprint(new RuntimeException, 500), '/var/www'))->toBeFalse();
});

it('falls back to the status code when there is no exception', function (): void {
    $generator = messageNumber();

    expect($generator->fingerprint(null, 404))->toBe('http:404');
    expect($generator->for(null, 404))->toBe($generator->for(null, 404));
    expect($generator->for(null, 404))->not->toBe($generator->for(null, 500));
});

it('can include the exception class in the fingerprint', function (): void {
    $with = messageNumber(['include_exception_class' => true]);

    expect($with->fingerprint(new RuntimeException, 500))->toContain(RuntimeException::class);
    expect(messageNumber()->fingerprint(new RuntimeException, 500))->not->toContain(RuntimeException::class);
});

it('changes every number when the salt changes', function (): void {
    $exception = new RuntimeException;

    expect(messageNumber(['salt' => 'one'])->for($exception, 500))
        ->not->toBe(messageNumber(['salt' => 'two'])->for($exception, 500));
});

it('respects the configured prefix and separator', function (): void {
    $generator = messageNumber(['prefix' => 'ACME', 'separator' => '.']);

    expect($generator->for(new RuntimeException, 500))->toStartWith('ACME.');
});

it('omits the prefix when it is empty', function (): void {
    expect(messageNumber(['prefix' => null])->for(new RuntimeException, 500))
        ->not->toContain('-');
});

it('renders every configured alphabet at the requested length', function (MessageNumberAlphabet $alphabet, string $pattern): void {
    $number = messageNumber(['alphabet' => $alphabet, 'length' => 8, 'prefix' => null])
        ->for(new RuntimeException, 500);

    expect($number)->toMatch($pattern)->and($number)->toHaveLength(8);
})->with([
    [MessageNumberAlphabet::Hex, '/^[0-9A-F]{8}$/'],
    [MessageNumberAlphabet::Numeric, '/^[0-9]{8}$/'],
    [MessageNumberAlphabet::Base36, '/^[0-9A-Z]{8}$/'],
]);

it('returns null when disabled', function (): void {
    expect(messageNumber(['enabled' => false])->for(new RuntimeException, 500))->toBeNull();
});

it('walks to the first application frame with the app strategy', function (): void {
    $generator = messageNumber(['origin' => OriginStrategy::Application], basePath: dirname(__DIR__, 2));

    // Thrown from inside a closure in this file, so the app frame is this file.
    $exception = (static fn (): RuntimeException => new RuntimeException('deep'))();

    $origin = $generator->origin($exception);

    expect($origin)->not->toBeNull()
        ->and($origin['file'])->toContain('tests/Unit/MessageNumberTest.php');
});

it('uses the deepest previous exception with the root strategy', function (): void {
    $root = new LogicException('root cause');
    $wrapper = new RuntimeException('wrapped', 0, $root);

    $generator = messageNumber(['origin' => OriginStrategy::RootCause]);

    expect($generator->origin($wrapper)['line'])->toBe($root->getLine());
});

it('normalises paths relative to the project root', function (): void {
    $generator = messageNumber(basePath: '/var/www/releases/20240101');

    expect($generator->normalisePath('/var/www/releases/20240101/app/Http/Controllers/PostController.php'))
        ->toBe('app/Http/Controllers/PostController.php');
});

it('normalises vendor paths so framework exceptions stay stable', function (): void {
    // A vendor file lives outside the base path on some setups; anchoring on
    // /vendor/ keeps the fingerprint identical either way.
    $inside = messageNumber(basePath: '/var/www/releases/20240101');
    $outside = messageNumber(basePath: '/srv/app');

    expect($inside->normalisePath('/var/www/releases/20240101/vendor/laravel/framework/src/Foo.php'))
        ->toBe('vendor/laravel/framework/src/Foo.php')
        ->and($outside->normalisePath('/var/www/releases/20240101/vendor/laravel/framework/src/Foo.php'))
        ->toBe('vendor/laravel/framework/src/Foo.php');
});

it('normalises windows separators', function (): void {
    $generator = messageNumber(basePath: 'C:\\projects\\app');

    expect($generator->normalisePath('C:\\projects\\app\\app\\Models\\User.php'))
        ->toBe('app/Models/User.php');
});
