<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Contracts;

use Illuminate\Http\Request;

/**
 * Resolves the correlation id shown on the error page and echoed in the
 * response headers.
 *
 * Bind your own to read a header this package does not know about, or to reuse
 * an id your APM agent has already assigned.
 */
interface RequestIdResolver
{
    /**
     * Must be idempotent: calling it twice for one request returns one id.
     */
    public function resolve(Request $request): ?string;

    /**
     * The response header the id is echoed on, or null to send none.
     */
    public function responseHeader(): ?string;
}
