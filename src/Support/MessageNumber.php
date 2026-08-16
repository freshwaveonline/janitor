<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Support;

use Throwable;
use Vvdboogaard\ErrorPages\Enums\MessageNumberAlphabet;
use Vvdboogaard\ErrorPages\Enums\OriginStrategy;

/**
 * Deterministic, human-quotable identifier for an error.
 *
 * The number is derived purely from *where* the error happened — a normalised
 * `path/to/file.php:123` fingerprint — so the same failure always produces the
 * same number across servers, deploys and users. That is what makes it useful
 * in a support ticket: two reports carrying `ERR-3F9A2C` are the same bug.
 *
 * Absolute paths are stripped to project-relative paths first, otherwise the
 * number would change whenever the deploy directory changes (`releases/1234`).
 */
final class MessageNumber
{
    /**
     * @param  array{
     *     enabled?: bool,
     *     prefix?: string|null,
     *     separator?: string,
     *     length?: int,
     *     alphabet?: string|MessageNumberAlphabet,
     *     algorithm?: string,
     *     origin?: string|OriginStrategy,
     *     include_exception_class?: bool,
     *     include_status_code?: bool,
     *     salt?: string|null,
     * }  $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly string $basePath = '',
    ) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * Full, formatted message number (e.g. `ERR-3F9A2C`).
     */
    public function for(?Throwable $exception, int $statusCode): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->format($this->hash($this->fingerprint($exception, $statusCode)));
    }

    /**
     * The raw string that gets hashed. Exposed for testing and for logging, so
     * support can see *why* two errors share a number.
     */
    public function fingerprint(?Throwable $exception, int $statusCode): string
    {
        $parts = [];

        $origin = $exception !== null ? $this->origin($exception) : null;

        if ($origin !== null) {
            $parts[] = $origin['file'].':'.$origin['line'];
        } else {
            // Routing-level errors (404, 405, …) carry no meaningful source
            // location, so the status code itself is the fingerprint.
            $parts[] = 'http:'.$statusCode;
        }

        if ($exception !== null && ($this->config['include_exception_class'] ?? false)) {
            $parts[] = $exception::class;
        }

        if ($this->config['include_status_code'] ?? false) {
            $parts[] = (string) $statusCode;
        }

        $salt = $this->config['salt'] ?? null;

        if (is_string($salt) && $salt !== '') {
            $parts[] = $salt;
        }

        return implode('|', $parts);
    }

    /**
     * Resolve the file/line pair that identifies this error.
     *
     * @return array{file: string, line: int}|null
     */
    public function origin(Throwable $exception): ?array
    {
        $strategy = OriginStrategy::parse($this->config['origin'] ?? null);

        if ($strategy === OriginStrategy::RootCause) {
            $exception = $this->rootCause($exception);
        }

        if ($strategy === OriginStrategy::Application) {
            $frame = $this->firstApplicationFrame($exception);

            if ($frame !== null) {
                return $frame;
            }
        }

        $file = $exception->getFile();

        if ($file === '') {
            return null;
        }

        return [
            'file' => $this->normalisePath($file),
            'line' => $exception->getLine(),
        ];
    }

    /**
     * Make the path stable across machines and deploys.
     *
     * `/var/www/releases/20240101/app/Http/Foo.php` → `app/Http/Foo.php`
     * `/var/www/vendor/laravel/framework/src/Bar.php` → `vendor/laravel/framework/src/Bar.php`
     */
    public function normalisePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if ($this->basePath !== '') {
            $base = rtrim(str_replace('\\', '/', $this->basePath), '/').'/';

            if (str_starts_with($path, $base)) {
                return substr($path, strlen($base));
            }
        }

        // Deploy paths differ per release; anchoring on /vendor/ keeps the
        // fingerprint stable for framework-thrown exceptions too.
        $vendorPosition = strrpos($path, '/vendor/');

        if ($vendorPosition !== false) {
            return substr($path, $vendorPosition + 1);
        }

        return ltrim($path, '/');
    }

    private function rootCause(Throwable $exception): Throwable
    {
        $guard = 0;

        while (($previous = $exception->getPrevious()) !== null && $guard++ < 32) {
            $exception = $previous;
        }

        return $exception;
    }

    /**
     * The throw site itself when it lives in application code, otherwise the
     * first stack frame outside of vendor/.
     *
     * @return array{file: string, line: int}|null
     */
    private function firstApplicationFrame(Throwable $exception): ?array
    {
        $file = $exception->getFile();

        if ($file !== '' && $this->isApplicationPath($file)) {
            return ['file' => $this->normalisePath($file), 'line' => $exception->getLine()];
        }

        foreach ($exception->getTrace() as $frame) {
            $frameFile = $frame['file'] ?? null;
            $frameLine = $frame['line'] ?? null;

            if (! is_string($frameFile) || ! is_int($frameLine)) {
                continue;
            }

            if ($this->isApplicationPath($frameFile)) {
                return ['file' => $this->normalisePath($frameFile), 'line' => $frameLine];
            }
        }

        return null;
    }

    private function isApplicationPath(string $path): bool
    {
        $normalised = str_replace('\\', '/', $path);

        if (str_contains($normalised, '/vendor/')) {
            return false;
        }

        if ($this->basePath === '') {
            return true;
        }

        return str_starts_with($normalised, rtrim(str_replace('\\', '/', $this->basePath), '/').'/');
    }

    private function hash(string $fingerprint): string
    {
        $algorithm = (string) ($this->config['algorithm'] ?? 'crc32b');

        if (! in_array($algorithm, hash_algos(), true)) {
            $algorithm = 'crc32b';
        }

        $length = max(3, min(32, (int) ($this->config['length'] ?? 6)));
        $alphabet = MessageNumberAlphabet::parse($this->config['alphabet'] ?? null);

        $hex = hash($algorithm, $fingerprint);

        return match ($alphabet) {
            MessageNumberAlphabet::Hex => strtoupper(substr($hex, 0, $length)),
            MessageNumberAlphabet::Numeric => $this->toBase($hex, 10, $length),
            MessageNumberAlphabet::Base36 => strtoupper($this->toBase($hex, 36, $length)),
        };
    }

    /**
     * Convert the leading bytes of a hex digest into another base, zero padded
     * to exactly $length characters.
     */
    private function toBase(string $hex, int $base, int $length): string
    {
        // 13 hex chars ≈ 52 bits: safely inside PHP_INT_MAX on 64-bit and enough
        // entropy for any practical message number length.
        $slice = substr($hex, 0, 13);
        $value = (int) hexdec($slice);

        $converted = base_convert((string) $value, 10, $base);
        $converted = substr($converted, -$length);

        return str_pad($converted, $length, '0', STR_PAD_LEFT);
    }

    private function format(string $hash): string
    {
        $prefix = $this->config['prefix'] ?? null;

        if (! is_string($prefix) || trim($prefix) === '') {
            return $hash;
        }

        $separator = (string) ($this->config['separator'] ?? '-');

        return trim($prefix).$separator.$hash;
    }
}
