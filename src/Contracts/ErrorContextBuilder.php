<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Contracts;

use FreshwaveOnline\Janitor\Data\ErrorContext;
use Illuminate\Http\Request;
use Throwable;

/**
 * Resolves a Throwable into the single value object every presentation — the
 * page, the JSON response and the Livewire pop-up — renders from.
 *
 * Bind your own (or extend ErrorContextFactory, whose methods are all
 * protected) to change where the copy comes from: a CMS, a database table, or
 * a per-tenant override table rather than translation files.
 */
interface ErrorContextBuilder
{
    public function make(Request $request, ?Throwable $exception, ?int $statusCode = null): ErrorContext;

    /**
     * The HTTP status this exception should be presented as.
     */
    public function statusCode(?Throwable $exception): int;
}
