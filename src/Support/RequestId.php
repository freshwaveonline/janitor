<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves the correlation id for the current request.
 *
 * Load balancers, CDNs and APM agents all use their own header name, so the
 * list is configurable and checked in order. When nothing upstream supplied
 * one we generate it ourselves, which still lets a user quote an id that
 * matches the application log for that exact request.
 */
final class RequestId
{
    public const ATTRIBUTE = 'error-pages.request_id';

    /**
     * Header values end up in HTML and in log files; keep them boring.
     */
    private const SAFE_PATTERN = '/^[A-Za-z0-9._:\-\/+=]{1,128}$/';

    /**
     * @param  array{enabled?: bool, headers?: list<string>, generate?: bool, generator?: string, response_header?: string|null}  $config
     */
    public function __construct(private readonly array $config = []) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        /** @var list<string> $headers */
        $headers = $this->config['headers'] ?? ['X-Request-Id'];

        return $headers;
    }

    public function responseHeader(): ?string
    {
        $header = $this->config['response_header'] ?? 'X-Request-Id';

        return is_string($header) && $header !== '' ? $header : null;
    }

    /**
     * Resolve — and memoise on the request — the correlation id.
     */
    public function resolve(Request $request): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $existing = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = $this->fromHeaders($request) ?? $this->generate();

        if ($id !== null) {
            $request->attributes->set(self::ATTRIBUTE, $id);
        }

        return $id;
    }

    public function fromHeaders(Request $request): ?string
    {
        foreach ($this->headers() as $header) {
            $value = $request->headers->get($header);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $value = $this->sanitize($header, $value);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public function generate(): ?string
    {
        if (! ($this->config['generate'] ?? true)) {
            return null;
        }

        return match ((string) ($this->config['generator'] ?? 'uuid')) {
            'ulid' => (string) Str::ulid(),
            'random' => Str::lower(Str::random(24)),
            default => (string) Str::uuid(),
        };
    }

    private function sanitize(string $header, string $value): ?string
    {
        $value = trim($value);

        // AWS ALB sends `Root=1-63441c4a-abcdef012345678912345678`; the trace id
        // is the part support actually needs.
        if (strcasecmp($header, 'X-Amzn-Trace-Id') === 0 && preg_match('/Root=([^;\s]+)/', $value, $matches) === 1) {
            $value = $matches[1];
        }

        // Google Cloud sends `TRACE_ID/SPAN_ID;o=1`.
        if (strcasecmp($header, 'X-Cloud-Trace-Context') === 0) {
            $value = explode('/', $value)[0];
        }

        // W3C traceparent: `00-<trace-id>-<span-id>-01`.
        if (strcasecmp($header, 'traceparent') === 0) {
            $segments = explode('-', $value);
            $value = $segments[1] ?? $value;
        }

        $value = substr($value, 0, 128);

        return preg_match(self::SAFE_PATTERN, $value) === 1 ? $value : null;
    }
}
