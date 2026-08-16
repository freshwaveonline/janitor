<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Throwable;

/**
 * Works out when the visitor may sensibly try again.
 *
 * Bind your own to derive the moment from something this package cannot see —
 * a queue backlog, a deploy window, a third-party status feed.
 */
interface RetryAfterResolver
{
    /**
     * A moment in the future, or null when there is nothing useful to show.
     *
     * Returning a moment that has already passed, or one so far away that the
     * visitor cannot act on it, is the implementation's job to filter out.
     */
    public function resolve(?Throwable $exception, ?Request $request = null): ?CarbonInterface;
}
