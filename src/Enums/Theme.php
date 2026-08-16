<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Enums;

/**
 * Colour scheme used by the error pages.
 */
enum Theme: string
{
    /** Follow the visitor's `prefers-color-scheme`. */
    case Auto = 'auto';

    case Light = 'light';

    case Dark = 'dark';

    /**
     * Value for the `color-scheme` CSS property / meta tag.
     */
    public function colorScheme(): string
    {
        return match ($this) {
            self::Auto => 'light dark',
            self::Light => 'light',
            self::Dark => 'dark',
        };
    }

    public static function parse(mixed $value, self $fallback = self::Auto): self
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
