<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Vvdboogaard\ErrorPages\Contracts\RetryAfterResolver;

/**
 * Works out when the visitor may sensibly try again.
 *
 * Sources, in order of trust:
 *   1. `Retry-After` on the exception (rate limiter, 503, custom aborts)
 *   2. `X-RateLimit-Reset` (Laravel's ThrottleRequests sets this too)
 *   3. `MaintenanceModeException::$willBeAvailableAt`
 *   4. `Retry-After` already present on the request's own response headers
 */
class RetryAfter implements RetryAfterResolver
{
    /**
     * @param  array{enabled?: bool, max_seconds?: int|null, headers?: list<string>}  $config
     */
    public function __construct(private readonly array $config = []) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    public function resolve(?Throwable $exception, ?Request $request = null): ?CarbonInterface
    {
        if (! $this->enabled() || $exception === null) {
            return null;
        }

        $moment = $this->fromMaintenanceMode($exception)
            ?? $this->fromExceptionHeaders($exception)
            ?? $this->fromRequest($request);

        if ($moment === null) {
            return null;
        }

        $now = CarbonImmutable::now();

        if ($moment->lessThanOrEqualTo($now)) {
            return null;
        }

        $max = $this->config['max_seconds'] ?? null;

        // A `Retry-After` measured in days is not actionable information for a
        // visitor; treat it as "no useful moment" rather than showing a countdown
        // that will never finish while the tab is open.
        if (is_int($max) && $max > 0 && $now->diffInSeconds($moment) > $max) {
            return null;
        }

        return $moment;
    }

    /**
     * `Retry-After` is either delta-seconds or an HTTP-date (RFC 7231).
     */
    public function parse(?string $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return CarbonImmutable::now()->addSeconds((int) $value);
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : CarbonImmutable::createFromTimestamp($timestamp);
    }

    private function fromMaintenanceMode(Throwable $exception): ?CarbonInterface
    {
        if (! property_exists($exception, 'willBeAvailableAt')) {
            return null;
        }

        /** @var mixed $value */
        $value = $exception->willBeAvailableAt;

        return $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)
            : $this->parse(is_scalar($value) ? (string) $value : null);
    }

    private function fromExceptionHeaders(Throwable $exception): ?CarbonInterface
    {
        if (! $exception instanceof HttpExceptionInterface) {
            return null;
        }

        return $this->fromHeaderBag($exception->getHeaders());
    }

    private function fromRequest(?Request $request): ?CarbonInterface
    {
        if ($request === null) {
            return null;
        }

        /** @var mixed $headers */
        $headers = $request->attributes->get('error-pages.retry_headers');

        return is_array($headers) ? $this->fromHeaderBag($headers) : null;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function fromHeaderBag(array $headers): ?CarbonInterface
    {
        /** @var list<string> $names */
        $names = $this->config['headers'] ?? ['Retry-After', 'X-RateLimit-Reset'];

        // Normalise once so lookups are case insensitive.
        $normalised = [];

        foreach ($headers as $key => $value) {
            $normalised[strtolower((string) $key)] = is_array($value) ? ($value[0] ?? null) : $value;
        }

        foreach ($names as $name) {
            $value = $normalised[strtolower($name)] ?? null;

            if ($value === null || (! is_scalar($value))) {
                continue;
            }

            $moment = strcasecmp($name, 'X-RateLimit-Reset') === 0
                ? $this->parseTimestamp((string) $value)
                : $this->parse((string) $value);

            if ($moment !== null) {
                return $moment;
            }
        }

        return null;
    }

    /**
     * `X-RateLimit-Reset` is an absolute UNIX timestamp.
     */
    private function parseTimestamp(string $value): ?CarbonInterface
    {
        if (! ctype_digit(trim($value))) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $value);
    }
}
