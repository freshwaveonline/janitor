<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Enums;

/**
 * Character set used to render the hashed message number.
 */
enum MessageNumberAlphabet: string
{
    /** 0-9 a-f, e.g. ERR-3F9A2C. */
    case Hex = 'hex';

    /** Digits only, e.g. ERR-482913. Easiest to read out loud. */
    case Numeric = 'numeric';

    /** 0-9 a-z, e.g. ERR-K3D9ZQ. Most entropy per character. */
    case Base36 = 'base36';

    public static function parse(mixed $value, self $fallback = self::Hex): self
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
