<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Enums;

/**
 * Which file/line pair is used as the fingerprint for the message number.
 */
enum OriginStrategy: string
{
    /**
     * The first stack frame that lives inside the application (outside vendor/).
     * Most useful default: a QueryException thrown deep inside Illuminate still
     * fingerprints to the line in your own code that triggered it.
     */
    case Application = 'app';

    /** The exact location where the exception was constructed. */
    case Thrown = 'thrown';

    /** The deepest previous exception in the chain. */
    case RootCause = 'root';

    public static function parse(mixed $value, self $fallback = self::Application): self
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
