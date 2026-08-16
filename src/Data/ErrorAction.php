<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A single call-to-action button on the error page.
 *
 * @implements Arrayable<string, mixed>
 */
final class ErrorAction implements Arrayable
{
    public const STYLE_PRIMARY = 'primary';

    public const STYLE_SECONDARY = 'secondary';

    public const STYLE_GHOST = 'ghost';

    /**
     * @param  string  $behaviour  'link', 'back', 'reload' — drives the client-side handler.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $url = null,
        public readonly ?string $icon = null,
        public readonly string $style = self::STYLE_SECONDARY,
        public readonly string $behaviour = 'link',
        public readonly bool $external = false,
        public readonly ?string $description = null,
        /** Disabled until the retry moment has passed. */
        public readonly bool $waitsForRetry = false,
    ) {}

    public function isPrimary(): bool
    {
        return $this->style === self::STYLE_PRIMARY;
    }

    /**
     * `<a>` for real navigation, `<button>` for history/reload behaviours.
     */
    public function tag(): string
    {
        return $this->behaviour === 'link' ? 'a' : 'button';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->url,
            'icon' => $this->icon,
            'style' => $this->style,
            'behaviour' => $this->behaviour,
            'external' => $this->external,
            'description' => $this->description,
            'waits_for_retry' => $this->waitsForRetry,
        ];
    }
}
