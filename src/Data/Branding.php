<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Everything about *whose* application this error page belongs to.
 *
 * Kept as one value object rather than a bag of config lookups so a
 * multi-tenant application can produce the whole thing from its own tenant
 * record in a single method — see BrandingResolver.
 *
 * @implements Arrayable<string, mixed>
 */
final class Branding implements Arrayable
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $logo = null,
        public readonly ?string $logoDark = null,
        public readonly int $logoHeight = 32,
        public readonly bool $showNameBesideLogo = false,
        /** Base accent colour; any hex, rgb() or "r, g, b" string. */
        public readonly ?string $primaryColor = null,
        /** Optional per-scheme overrides; null falls back to $primaryColor. */
        public readonly ?string $primaryColorLight = null,
        public readonly ?string $primaryColorDark = null,
        public readonly bool $autoContrast = true,
        public readonly ?string $homeUrl = null,
        public readonly ?string $loginUrl = null,
        /** Already filtered by status code; null means "do not offer support here". */
        public readonly ?string $supportEmail = null,
        public readonly ?string $statusPageUrl = null,
    ) {}

    public function hasMark(): bool
    {
        return $this->logo !== null || $this->name !== null;
    }

    /**
     * @return array{primary: string|null, light: string|null, dark: string|null, auto_contrast: bool}
     */
    public function colors(): array
    {
        return [
            'primary' => $this->primaryColor,
            'light' => $this->primaryColorLight,
            'dark' => $this->primaryColorDark,
            'auto_contrast' => $this->autoContrast,
        ];
    }

    /**
     * Copy this branding with a few values replaced — handy when decorating the
     * default resolver instead of replacing it wholesale.
     */
    public function with(mixed ...$overrides): self
    {
        /** @var array<string, mixed> $overrides */
        return new self(
            name: $overrides['name'] ?? $this->name,
            logo: $overrides['logo'] ?? $this->logo,
            logoDark: $overrides['logoDark'] ?? $this->logoDark,
            logoHeight: $overrides['logoHeight'] ?? $this->logoHeight,
            showNameBesideLogo: $overrides['showNameBesideLogo'] ?? $this->showNameBesideLogo,
            primaryColor: $overrides['primaryColor'] ?? $this->primaryColor,
            primaryColorLight: $overrides['primaryColorLight'] ?? $this->primaryColorLight,
            primaryColorDark: $overrides['primaryColorDark'] ?? $this->primaryColorDark,
            autoContrast: $overrides['autoContrast'] ?? $this->autoContrast,
            homeUrl: $overrides['homeUrl'] ?? $this->homeUrl,
            loginUrl: $overrides['loginUrl'] ?? $this->loginUrl,
            supportEmail: $overrides['supportEmail'] ?? $this->supportEmail,
            statusPageUrl: $overrides['statusPageUrl'] ?? $this->statusPageUrl,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'logo' => $this->logo,
            'logo_dark' => $this->logoDark,
            'logo_height' => $this->logoHeight,
            'show_name_beside_logo' => $this->showNameBesideLogo,
            'primary_color' => $this->primaryColor,
            'home_url' => $this->homeUrl,
            'login_url' => $this->loginUrl,
            'support_email' => $this->supportEmail,
            'status_page_url' => $this->statusPageUrl,
        ];
    }
}
