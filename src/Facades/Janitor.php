<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Facades;

use FreshwaveOnline\Janitor\ErrorPageRenderer;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Symfony\Component\HttpFoundation\Response|null render(\Illuminate\Http\Request $request, \Throwable $exception)
 * @method static \Symfony\Component\HttpFoundation\Response renderContext(\Illuminate\Http\Request $request, \FreshwaveOnline\Janitor\Data\ErrorContext $context)
 * @method static bool shouldHandle(\Illuminate\Http\Request $request, \Throwable $exception)
 * @method static int statusFor(\Throwable $exception)
 * @method static string viewName(int $status)
 * @method static array<string, mixed> livewirePayload(\FreshwaveOnline\Janitor\Data\ErrorContext $context)
 *
 * @see ErrorPageRenderer
 */
class Janitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ErrorPageRenderer::class;
    }
}
