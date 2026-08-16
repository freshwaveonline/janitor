<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Vvdboogaard\ErrorPages\Data\ErrorContext;

/**
 * Decides whether this package renders a given exception, and in which shape.
 *
 * Registered as a `renderable` callback on Laravel's exception handler, so
 * returning null hands the exception straight back to the framework.
 */
interface ErrorRenderer
{
    /**
     * @return Response|null Null means "not ours", and Laravel takes over.
     */
    public function render(Request $request, Throwable $exception): ?Response;

    /**
     * Render a context directly, outside of the exception handler.
     */
    public function renderContext(Request $request, ErrorContext $context): Response;

    public function shouldHandle(Request $request, Throwable $exception): bool;

    /**
     * The HTTP status this exception is presented as.
     */
    public function statusFor(Throwable $exception): int;

    /**
     * The view used for a status code, honouring published and per-code overrides.
     */
    public function viewName(int $status): string;

    /**
     * The payload the injected Livewire handler renders its pop-up from.
     *
     * @return array<string, mixed>
     */
    public function livewirePayload(ErrorContext $context): array;
}
