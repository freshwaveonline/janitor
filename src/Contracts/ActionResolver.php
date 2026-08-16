<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Contracts;

use FreshwaveOnline\Janitor\Data\ErrorAction;
use FreshwaveOnline\Janitor\Data\ErrorContext;
use Illuminate\Http\Request;

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
