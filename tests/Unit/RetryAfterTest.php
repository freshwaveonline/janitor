<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use FreshwaveOnline\Janitor\Support\RetryAfter;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

function retryAfter(array $overrides = []): RetryAfter
{
    return new RetryAfter(array_merge([
        'enabled' => true,
        'headers' => ['Retry-After', 'X-RateLimit-Reset'],
        'max_seconds' => 86400,
    ], $overrides));
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-08-16 12:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('reads delta-seconds from the rate limiter', function (): void {
    $moment = retryAfter()->resolve(new TooManyRequestsHttpException(90));

    expect($moment?->toDateTimeString())->toBe('2026-08-16 12:01:30');
});

it('reads the retry moment from a 503', function (): void {
    $moment = retryAfter()->resolve(new ServiceUnavailableHttpException(600));

    expect($moment?->toDateTimeString())->toBe('2026-08-16 12:10:00');
});

it('parses an HTTP-date Retry-After header', function (): void {
    $exception = new ServiceUnavailableHttpException('Sun, 16 Aug 2026 12:30:00 GMT');

    expect(retryAfter()->resolve($exception)?->utc()->toDateTimeString())
        ->toBe('2026-08-16 12:30:00');
});

it('parses the X-RateLimit-Reset timestamp when Retry-After is absent', function (): void {
    $exception = new TooManyRequestsHttpException(null, '', null, 0, [
        'X-RateLimit-Reset' => (string) CarbonImmutable::parse('2026-08-16 12:05:00')->getTimestamp(),
    ]);

    expect(retryAfter()->resolve($exception)?->utc()->toDateTimeString())
        ->toBe('2026-08-16 12:05:00');
});

it('reads the retry moment out of maintenance mode', function (): void {
    // This is exactly what `php artisan down --retry=600` produces in
    // Laravel 11+: a plain HttpException carrying a Retry-After header.
    $exception = new HttpException(503, 'Service Unavailable', null, ['Retry-After' => '600']);

    expect(retryAfter()->resolve($exception)?->toDateTimeString())
        ->toBe('2026-08-16 12:10:00');
});

it('reads a willBeAvailableAt property when an exception exposes one', function (): void {
    // Older Laravel releases (and custom exceptions) carry the moment as a
    // property rather than a header.
    $exception = new class('Service Unavailable') extends RuntimeException
    {
        public CarbonImmutable $willBeAvailableAt;

        public function __construct(string $message)
        {
            parent::__construct($message);

            $this->willBeAvailableAt = CarbonImmutable::parse('2026-08-16 12:15:00');
        }
    };

    expect(retryAfter()->resolve($exception)?->toDateTimeString())
        ->toBe('2026-08-16 12:15:00');
});

it('ignores a moment that has already passed', function (): void {
    $exception = new ServiceUnavailableHttpException('Sun, 16 Aug 2026 11:00:00 GMT');

    expect(retryAfter()->resolve($exception))->toBeNull();
});

it('ignores a wait longer than the configured maximum', function (): void {
    // "Try again in 3 days" is not something a visitor can act on, so no
    // countdown is shown at all.
    expect(retryAfter(['max_seconds' => 3600])->resolve(new ServiceUnavailableHttpException(3 * 86400)))
        ->toBeNull();
});

it('returns null for exceptions that carry no retry information', function (): void {
    expect(retryAfter()->resolve(new RuntimeException('boom')))->toBeNull()
        ->and(retryAfter()->resolve(null))->toBeNull();
});

it('returns null when disabled', function (): void {
    expect(retryAfter(['enabled' => false])->resolve(new TooManyRequestsHttpException(90)))->toBeNull();
});

it('parses raw header values', function (): void {
    $resolver = retryAfter();

    expect($resolver->parse('120')?->toDateTimeString())->toBe('2026-08-16 12:02:00')
        ->and($resolver->parse(''))->toBeNull()
        ->and($resolver->parse(null))->toBeNull()
        ->and($resolver->parse('not a date'))->toBeNull();
});
