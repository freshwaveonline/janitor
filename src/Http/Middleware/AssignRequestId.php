<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vvdboogaard\ErrorPages\Support\RequestId;

/**
 * Assigns the correlation id at the very start of the request.
 *
 * Doing it here rather than at render time means the id is already available to
 * every log line written during the request, so the number a visitor reads off
 * the error page is the one you grep for.
 */
class AssignRequestId
{
    public function __construct(private readonly RequestId $requestId) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->requestId->resolve($request);

        /** @var Response $response */
        $response = $next($request);

        $header = $this->requestId->responseHeader();

        if ($id !== null && $header !== null && ! $response->headers->has($header)) {
            $response->headers->set($header, $id);
        }

        return $response;
    }
}
