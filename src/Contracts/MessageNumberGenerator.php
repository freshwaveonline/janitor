<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Contracts;

use Throwable;

/**
 * Turns an error into the short code a visitor can quote back to support.
 *
 * Bind your own implementation when your organisation already has an error-code
 * scheme, or when the number must come from a service rather than a hash:
 *
 *     $this->app->bind(MessageNumberGenerator::class, MyIncidentCodes::class);
 *
 * Whatever you return must be *stable*: the same failure has to produce the same
 * code on every server and after every deploy, otherwise two tickets about one
 * bug carry two different numbers and the whole feature is noise.
 */
interface MessageNumberGenerator
{
    /**
     * The formatted message number, or null to show none.
     */
    public function for(?Throwable $exception, int $statusCode): ?string;

    /**
     * The raw string the number is derived from.
     *
     * Exposed so it can be logged: when two errors share a number, this explains
     * why. Implementations that do not hash anything may return the number.
     */
    public function fingerprint(?Throwable $exception, int $statusCode): string;
}
