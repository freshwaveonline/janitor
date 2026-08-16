<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Contracts;

use Illuminate\Http\Request;
use Vvdboogaard\ErrorPages\Data\ErrorAction;
use Vvdboogaard\ErrorPages\Data\ErrorContext;

/**
 * Builds the call-to-action buttons for an error.
 *
 * The config covers static buttons. Bind your own implementation when the
 * buttons depend on runtime state — "Resume your checkout" pointing at the cart
 * the visitor just lost, for instance.
 */
interface ActionResolver
{
    /**
     * @param  ErrorContext  $context  Fully populated except for its actions.
     * @return list<ErrorAction>
     */
    public function for(ErrorContext $context, Request $request): array;
}
