<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Data;

use Illuminate\Contracts\Support\Arrayable;
use Throwable;

/**
 * The technical block shown on a 500 when the environment allows it.
 *
 * Deliberately a flat, pre-rendered value object: whatever ends up here is
 * about to be shown to a human and copied into a ticket, so it must contain
 * nothing that has not been consciously put in it (no request payloads, no
 * environment variables, no bound method arguments).
 *
 * @implements Arrayable<string, mixed>
 */
final class ExceptionDetails implements Arrayable
{
    /**
     * @param  list<array{file: string, line: int, call: string, vendor: bool}>  $frames
     */
    public function __construct(
        public readonly string $class,
        public readonly string $message,
        public readonly string $file,
        public readonly int $line,
        public readonly array $frames = [],
        public readonly ?string $previous = null,
    ) {}

    public static function fromThrowable(Throwable $exception, string $basePath = '', int $maxFrames = 12): self
    {
        $previous = $exception->getPrevious();

        return new self(
            class: $exception::class,
            message: $exception->getMessage(),
            file: self::relative($exception->getFile(), $basePath),
            line: $exception->getLine(),
            frames: self::frames($exception, $basePath, $maxFrames),
            previous: $previous !== null
                ? $previous::class.': '.$previous->getMessage()
                : null,
        );
    }

    public function location(): string
    {
        return $this->file.':'.$this->line;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'frames' => $this->frames,
            'previous' => $this->previous,
        ];
    }

    /**
     * @return list<array{file: string, line: int, call: string, vendor: bool}>
     */
    private static function frames(Throwable $exception, string $basePath, int $maxFrames): array
    {
        $frames = [];

        foreach ($exception->getTrace() as $frame) {
            if (count($frames) >= $maxFrames) {
                break;
            }

            $file = $frame['file'] ?? null;

            if (! is_string($file)) {
                continue;
            }

            $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');
            $relative = self::relative($file, $basePath);

            $frames[] = [
                'file' => $relative,
                'line' => (int) ($frame['line'] ?? 0),
                'call' => $call === '' ? '{closure}' : $call.'()',
                'vendor' => str_starts_with($relative, 'vendor/'),
            ];
        }

        return $frames;
    }

    private static function relative(string $path, string $basePath): string
    {
        $path = str_replace('\\', '/', $path);

        if ($basePath !== '') {
            $base = rtrim(str_replace('\\', '/', $basePath), '/').'/';

            if (str_starts_with($path, $base)) {
                return substr($path, strlen($base));
            }
        }

        $vendorPosition = strrpos($path, '/vendor/');

        return $vendorPosition !== false ? substr($path, $vendorPosition + 1) : $path;
    }
}
