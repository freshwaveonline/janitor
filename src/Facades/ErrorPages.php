<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Facades;

use Illuminate\Support\Facades\Facade;
use Vvdboogaard\ErrorPages\ErrorPageRenderer;

/**
 * @method static \Symfony\Component\HttpFoundation\Response|null render(\Illuminate\Http\Request $request, \Throwable $exception)
 * @method static \Symfony\Component\HttpFoundation\Response renderContext(\Illuminate\Http\Request $request, \Vvdboogaard\ErrorPages\Data\ErrorContext $context)
 * @method static bool shouldHandle(\Illuminate\Http\Request $request, \Throwable $exception)
 * @method static int statusFor(\Throwable $exception)
 * @method static string viewName(int $status)
 * @method static array<string, mixed> livewirePayload(\Vvdboogaard\ErrorPages\Data\ErrorContext $context)
 *
 * @see ErrorPageRenderer
 */
class ErrorPages extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ErrorPageRenderer::class;
    }
}
