<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Support;

/**
 * Minimal, dependency-free sRGB colour value object.
 *
 * Only what the error pages need: parsing, mixing, relative luminance and
 * WCAG contrast ratios so a configured primary colour can be nudged until it
 * is readable on both the light and the dark surface.
 */
final class Color
{
    private function __construct(
        public readonly int $red,
        public readonly int $green,
        public readonly int $blue,
    ) {}

    public static function make(int $red, int $green, int $blue): self
    {
        return new self(
            max(0, min(255, $red)),
            max(0, min(255, $green)),
            max(0, min(255, $blue)),
        );
    }

    /**
     * Accepts `#rgb`, `#rrggbb`, `rgb(r, g, b)` and Filament's `"r, g, b"` shade format.
     */
    public static function parse(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^#?([0-9a-f]{3})$/i', $value, $matches) === 1) {
            /** @var string $digits */
            $digits = $matches[1];

            return self::make(
                (int) hexdec($digits[0].$digits[0]),
                (int) hexdec($digits[1].$digits[1]),
                (int) hexdec($digits[2].$digits[2]),
            );
        }

        if (preg_match('/^#?([0-9a-f]{6})$/i', $value, $matches) === 1) {
            /** @var string $digits */
            $digits = $matches[1];

            return self::make(
                (int) hexdec(substr($digits, 0, 2)),
                (int) hexdec(substr($digits, 2, 2)),
                (int) hexdec(substr($digits, 4, 2)),
            );
        }

        // rgb(59, 130, 246) / rgb(59 130 246) / "59, 130, 246"
        if (preg_match('/^(?:rgba?\()?\s*(\d{1,3})\s*[,\s]\s*(\d{1,3})\s*[,\s]\s*(\d{1,3})/i', $value, $matches) === 1) {
            return self::make((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    public function toHex(): string
    {
        return sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);
    }

    /**
     * Space separated channels, ready for `rgb(var(--token) / 50%)`.
     */
    public function toChannels(): string
    {
        return "{$this->red} {$this->green} {$this->blue}";
    }

    public function toRgba(float $alpha): string
    {
        return sprintf('rgba(%d, %d, %d, %s)', $this->red, $this->green, $this->blue, rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.'));
    }

    /**
     * WCAG 2.1 relative luminance.
     */
    public function luminance(): float
    {
        $channel = static function (int $value): float {
            $srgb = $value / 255;

            return $srgb <= 0.04045
                ? $srgb / 12.92
                : (($srgb + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($this->red)
            + 0.7152 * $channel($this->green)
            + 0.0722 * $channel($this->blue);
    }

    public function contrastRatio(self $other): float
    {
        $a = $this->luminance();
        $b = $other->luminance();

        return $a > $b ? ($a + 0.05) / ($b + 0.05) : ($b + 0.05) / ($a + 0.05);
    }

    /**
     * @param  float  $weight  0.0 keeps $this, 1.0 becomes $other.
     */
    public function mix(self $other, float $weight): self
    {
        $weight = max(0.0, min(1.0, $weight));

        return self::make(
            (int) round($this->red + ($other->red - $this->red) * $weight),
            (int) round($this->green + ($other->green - $this->green) * $weight),
            (int) round($this->blue + ($other->blue - $this->blue) * $weight),
        );
    }

    public function lighten(float $amount): self
    {
        return $this->mix(self::make(255, 255, 255), $amount);
    }

    public function darken(float $amount): self
    {
        return $this->mix(self::make(0, 0, 0), $amount);
    }

    /**
     * Black or white, whichever is readable on top of this colour.
     */
    public function readableTextColor(): self
    {
        $white = self::make(255, 255, 255);
        $black = self::make(23, 23, 27);

        return $this->contrastRatio($white) >= $this->contrastRatio($black) ? $white : $black;
    }

    /**
     * Nudge this colour towards white or black until it reaches $target contrast
     * against $background. Returns the original colour when the target is already met.
     */
    public function ensureContrast(self $background, float $target = 3.0, int $steps = 20): self
    {
        if ($this->contrastRatio($background) >= $target) {
            return $this;
        }

        $towardsLight = $background->luminance() < 0.5;
        $candidate = $this;

        for ($i = 1; $i <= $steps; $i++) {
            $amount = $i / $steps * 0.9;
            $candidate = $towardsLight ? $this->lighten($amount) : $this->darken($amount);

            if ($candidate->contrastRatio($background) >= $target) {
                return $candidate;
            }
        }

        return $candidate;
    }
}
