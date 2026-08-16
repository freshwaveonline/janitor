<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Enums;

/**
 * Where the Livewire error pop-up is anchored inside the viewport.
 */
enum ModalPosition: string
{
    case TopLeft = 'top-left';
    case TopCenter = 'top-center';
    case TopRight = 'top-right';
    case MiddleLeft = 'middle-left';
    case Center = 'center';
    case MiddleRight = 'middle-right';
    case BottomLeft = 'bottom-left';
    case BottomCenter = 'bottom-center';
    case BottomRight = 'bottom-right';

    /**
     * Flexbox cross-axis alignment for the fixed overlay container.
     */
    public function alignItems(): string
    {
        return match (true) {
            in_array($this, [self::TopLeft, self::TopCenter, self::TopRight], true) => 'flex-start',
            in_array($this, [self::BottomLeft, self::BottomCenter, self::BottomRight], true) => 'flex-end',
            default => 'center',
        };
    }

    /**
     * Flexbox main-axis alignment for the fixed overlay container.
     */
    public function justifyContent(): string
    {
        return match (true) {
            in_array($this, [self::TopLeft, self::MiddleLeft, self::BottomLeft], true) => 'flex-start',
            in_array($this, [self::TopRight, self::MiddleRight, self::BottomRight], true) => 'flex-end',
            default => 'center',
        };
    }

    /**
     * Direction the pop-up slides in from, expressed as a CSS translate().
     */
    public function enterTransform(): string
    {
        return match (true) {
            $this === self::Center => 'scale(0.96)',
            in_array($this, [self::TopLeft, self::TopCenter, self::TopRight], true) => 'translateY(-12px)',
            in_array($this, [self::BottomLeft, self::BottomCenter, self::BottomRight], true) => 'translateY(12px)',
            $this === self::MiddleLeft => 'translateX(-12px)',
            default => 'translateX(12px)',
        };
    }

    /**
     * Whether the pop-up should be rendered as a centred dialog (with backdrop)
     * rather than as a corner toast.
     */
    public function isDialog(): bool
    {
        return $this === self::Center;
    }

    /**
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        return [
            '--ep-modal-align' => $this->alignItems(),
            '--ep-modal-justify' => $this->justifyContent(),
            '--ep-modal-enter' => $this->enterTransform(),
        ];
    }

    public static function parse(mixed $value, self $fallback = self::BottomRight): self
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
