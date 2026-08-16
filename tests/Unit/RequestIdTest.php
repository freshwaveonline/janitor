<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Vvdboogaard\ErrorPages\Support\RequestId;

function requestId(array $overrides = []): RequestId
{
    return new RequestId(array_merge([
        'enabled' => true,
        'headers' => ['X-Request-Id', 'X-Correlation-Id', 'X-Amzn-Trace-Id', 'X-Cloud-Trace-Context', 'traceparent'],
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

it('rejects header values that are not safe to render', function (string $value): void {
    $request = requestWith(['X-Request-Id' => $value]);

    // Falls through to a generated id rather than echoing the input back.
    expect(requestId()->resolve($request))->not->toBe($value);
})->with([
    '<script>alert(1)</script>',
    'id with spaces',
    'id"quoted"',
    str_repeat('a', 200),
]);

it('generates an id when nothing upstream supplied one', function (): void {
    $id = requestId()->resolve(requestWith([]));

    expect($id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
});

it('can generate ULIDs instead of UUIDs', function (): void {
    expect(requestId(['generator' => 'ulid'])->resolve(requestWith([])))->toHaveLength(26);
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
