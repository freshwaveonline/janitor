<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Enums;

/**
 * How an error that occurs during a Livewire round-trip is presented.
 */
enum LivewireErrorMode: string
{
    /** Render the pop-up anchored at the configured ModalPosition. */
    case Modal = 'modal';

    /** Replace the current document with the full error page. */
    case Page = 'page';

    /** Leave Livewire's own error handling untouched. */
    case Disabled = 'disabled';

    public static function parse(mixed $value, self $fallback = self::Modal): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::tryFrom($value) ?? $fallback;
        }

        return $fallback;
    }
}
