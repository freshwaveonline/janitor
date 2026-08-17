<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Support\RequestId;
use Illuminate\Http\Request;

function requestId(array $overrides = []): RequestId
{
    return new RequestId(array_merge([
        'enabled' => true,
        'headers' => ['X-Request-Id', 'X-Correlation-Id', 'X-Amzn-Trace-Id', 'X-Cloud-Trace-Context', 'CF-Ray', 'traceparent'],
        'generate' => true,
        'generator' => 'uuid',
        'response_header' => 'X-Request-Id',
    ], $overrides));
}

function requestWith(array $headers): Request
{
    $request = Request::create('/orders/42');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

it('reads the id from the configured headers in order', function (): void {
    $request = requestWith(['X-Correlation-Id' => 'corr-123', 'X-Request-Id' => 'req-456']);

    expect(requestId()->resolve($request))->toBe('req-456');
});

it('unwraps the AWS load balancer trace format', function (): void {
    $request = requestWith(['X-Amzn-Trace-Id' => 'Root=1-63441c4a-abcdef012345678912345678;Parent=abc;Sampled=1']);

    expect(requestId()->resolve($request))->toBe('1-63441c4a-abcdef012345678912345678');
});

it('unwraps the Google Cloud trace format', function (): void {
    $request = requestWith(['X-Cloud-Trace-Context' => '105445aa7843bc8bf206b12000100000/1;o=1']);

    expect(requestId()->resolve($request))->toBe('105445aa7843bc8bf206b12000100000');
});

it('extracts the trace id from a W3C traceparent header', function (): void {
    $request = requestWith(['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01']);

    expect(requestId()->resolve($request))->toBe('4bf92f3577b34da6a3ce929d0e0e4736');
});

it('reads each upstream format the config lists', function (string $header, string $value, string $expected): void {
    expect(requestId()->resolve(requestWith([$header => $value])))->toBe($expected);
})->with([
    'plain request id' => ['X-Request-Id', 'req-456', 'req-456'],
    'correlation id' => ['X-Correlation-Id', 'corr-123', 'corr-123'],
    'cloudflare ray' => ['CF-Ray', '7d8f6c1b2e3a4f56-AMS', '7d8f6c1b2e3a4f56-AMS'],
    'aws trace' => ['X-Amzn-Trace-Id', 'Root=1-63441c4a-abcdef012345678912345678', '1-63441c4a-abcdef012345678912345678'],
    'google trace' => ['X-Cloud-Trace-Context', '105445aa7843bc8bf206b12000100000/1;o=1', '105445aa7843bc8bf206b12000100000'],
    'w3c traceparent' => ['traceparent', '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01', '4bf92f3577b34da6a3ce929d0e0e4736'],
]);

it('falls through to the next header when one is unusable', function (): void {
    $request = requestWith([
        'X-Request-Id' => '<script>alert(1)</script>',
        'X-Correlation-Id' => 'corr-123',
    ]);

    expect(requestId()->resolve($request))->toBe('corr-123');
});

it('rejects header values that are not safe to render', function (string $value): void {
    $request = requestWith(['X-Request-Id' => $value]);

    // Falls through to a generated id rather than echoing the input back.
    expect(requestId()->resolve($request))
        ->not->toBe($value)
        ->toMatch('/^[0-9a-f-]{36}$/');
})->with([
    'markup' => ['<script>alert(1)</script>'],
    'spaces' => ['id with spaces'],
    'quotes' => ['id"quoted"'],
    'angle brackets' => ['<img src=x>'],
    'a semicolon' => ['id;drop'],
    'a percent escape' => ['%3Cscript%3E'],
    'a backtick' => ['id`whoami`'],
    'a newline' => ["id\nX-Injected: 1"],
    'a control character' => ["id\x07"],
    'a non-ASCII character' => ['idé'],
    'an emoji' => ['🙂'],
]);

it('strips a trailing null byte rather than losing the id', function (): void {
    expect(requestId()->resolve(requestWith(['X-Request-Id' => "req-9\0"])))->toBe('req-9');
});

it('truncates an over-long value to the length limit', function (): void {
    $request = requestWith(['X-Request-Id' => str_repeat('a', 300)]);

    // Long but otherwise boring is worth keeping, bounded — an id that never
    // ends would break the layout and bloat every log line carrying it.
    expect(requestId()->resolve($request))->toBe(str_repeat('a', 128));
});

it('keeps a value that sits exactly on the limit', function (): void {
    $exact = str_repeat('b', 128);

    expect(requestId()->resolve(requestWith(['X-Request-Id' => $exact])))->toBe($exact);
});

it('rejects an over-long value once truncation still leaves it unsafe', function (): void {
    $request = requestWith(['X-Request-Id' => '<script>'.str_repeat('a', 300)]);

    expect(requestId()->resolve($request))->toMatch('/^[0-9a-f-]{36}$/');
});

it('ignores an empty or whitespace-only header', function (string $value): void {
    expect(requestId()->resolve(requestWith(['X-Request-Id' => $value])))
        ->toMatch('/^[0-9a-f-]{36}$/');
})->with([
    'empty' => [''],
    'spaces' => ['   '],
]);

it('trims the surrounding whitespace off a usable value', function (): void {
    expect(requestId()->resolve(requestWith(['X-Request-Id' => '  req-9  '])))->toBe('req-9');
});

it('generates an id when nothing upstream supplied one', function (): void {
    $id = requestId()->resolve(requestWith([]));

    expect($id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
});

it('can generate ULIDs instead of UUIDs', function (): void {
    expect(requestId(['generator' => 'ulid'])->resolve(requestWith([])))->toHaveLength(26);
});

it('can generate a plain random string', function (): void {
    expect(requestId(['generator' => 'random'])->resolve(requestWith([])))
        ->toHaveLength(24)
        ->toMatch('/^[a-z0-9]{24}$/');
});

it('falls back to a UUID for an unknown generator', function (): void {
    expect(requestId(['generator' => 'wat'])->resolve(requestWith([])))->toMatch('/^[0-9a-f-]{36}$/');
});

it('reports the response header it was configured with', function (): void {
    expect(requestId()->responseHeader())->toBe('X-Request-Id')
        ->and(requestId(['response_header' => 'X-Trace'])->responseHeader())->toBe('X-Trace')
        ->and(requestId(['response_header' => null])->responseHeader())->toBeNull()
        ->and(requestId(['response_header' => ''])->responseHeader())->toBeNull();
});

it('returns the same id for the same request', function (): void {
    $resolver = requestId();
    $request = requestWith([]);

    expect($resolver->resolve($request))->toBe($resolver->resolve($request));
});

it('returns null when generation is off and no header is present', function (): void {
    expect(requestId(['generate' => false])->resolve(requestWith([])))->toBeNull();
});

it('returns null when disabled entirely', function (): void {
    expect(requestId(['enabled' => false])->resolve(requestWith(['X-Request-Id' => 'req-1'])))->toBeNull();
});
