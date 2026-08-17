<?php

declare(strict_types=1);

namespace Filament;

/**
 * Stand-in for a Filament panel, carrying only what the bridge reads.
 */
final class Panel
{
    /**
     * @param  array<string, mixed>  $colors
     */
    public function __construct(
        private readonly string $path = 'admin',
        private readonly mixed $brandName = null,
        private readonly mixed $brandLogo = null,
        private readonly array $colors = [],
        private readonly ?string $url = null,
        private readonly bool $hasLogin = false,
        private readonly ?string $loginUrl = null,
        private readonly bool $darkMode = true,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getBrandName(): mixed
    {
        return $this->brandName;
    }

    public function getBrandLogo(): mixed
    {
        return $this->brandLogo;
    }

    /**
     * @return array<string, mixed>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function hasLogin(): bool
    {
        return $this->hasLogin;
    }

    public function getLoginUrl(): ?string
    {
        return $this->loginUrl;
    }

    public function hasDarkMode(): bool
    {
        return $this->darkMode;
    }
}
