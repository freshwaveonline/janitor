<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Support;

use Throwable;

/**
 * Runs a lookup that may fail because the application is already broken.
 *
 * This package renders *after* something has gone wrong, so the systems it
 * reads from — the config repository, the translator, the router, the URL
 * generator, an optional package's facade — may be half-built or gone. Losing
 * one line of copy or one button is acceptable; throwing a second exception on
 * the way out is not, because the visitor then gets a blank 500 instead of the
 * page that was supposed to explain the first failure.
 */
final class Guard
{
    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @param  TValue  $default
     * @return TValue
     */
    public static function value(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $default;
        }
    }
}
