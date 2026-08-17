<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Http\Middleware;

use Closure;
use FreshwaveOnline\Janitor\Contracts\RequestIdResolver;
use FreshwaveOnline\Janitor\Support\Guard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns the correlation id at the very start of the request.
 *
 * Doing it here rather than at render time means the id is already available to
 * every log line written during the request, so the number a visitor reads off
 * the error page is the one you grep for.
 */
class AssignRequestId
{
    public function __construct(private readonly RequestIdResolver $requestId) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Global middleware, so this runs on every successful request too. A
        // resolver that cannot answer costs the correlation id and nothing else.
        $id = Guard::value(fn (): ?string => $this->requestId->resolve($request));

        /** @var Response $response */
        $response = $next($request);

        $header = Guard::value(fn (): ?string => $this->requestId->responseHeader());

        if ($id !== null && $header !== null && ! $response->headers->has($header)) {
            $response->headers->set($header, $id);
        }

        return $response;
    }
}
