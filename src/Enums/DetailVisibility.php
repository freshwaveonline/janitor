<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Enums;

/**
 * Controls whether the technical exception block is rendered on the page.
 */
enum DetailVisibility: string
{
    /** Decide based on app.debug and the configured environment allow-list. */
    case Auto = 'auto';

    /** Always render the exception block, regardless of environment. */
    case Always = 'always';

    /** Never render the exception block. */
    case Never = 'never';

    public static function parse(mixed $value, self $fallback = self::Auto): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? self::Always : self::Never;
        }

        if (is_string($value)) {
            return self::tryFrom($value) ?? $fallback;
        }

        return $fallback;
    }
}
