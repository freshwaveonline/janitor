<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Data;

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
     *
     * Overrides are passed as named arguments (`$branding->with(name: 'Acme')`).
     * A value of the wrong type is ignored rather than fatal: this runs while
     * the application is already failing.
     */
    public function with(mixed ...$overrides): self
    {
        return new self(
            name: $this->overrideString($overrides, 'name', $this->name),
            logo: $this->overrideString($overrides, 'logo', $this->logo),
            logoDark: $this->overrideString($overrides, 'logoDark', $this->logoDark),
            logoHeight: $this->overrideInt($overrides, 'logoHeight', $this->logoHeight),
            showNameBesideLogo: $this->overrideBool($overrides, 'showNameBesideLogo', $this->showNameBesideLogo),
            primaryColor: $this->overrideString($overrides, 'primaryColor', $this->primaryColor),
            primaryColorLight: $this->overrideString($overrides, 'primaryColorLight', $this->primaryColorLight),
            primaryColorDark: $this->overrideString($overrides, 'primaryColorDark', $this->primaryColorDark),
            autoContrast: $this->overrideBool($overrides, 'autoContrast', $this->autoContrast),
            homeUrl: $this->overrideString($overrides, 'homeUrl', $this->homeUrl),
            loginUrl: $this->overrideString($overrides, 'loginUrl', $this->loginUrl),
            supportEmail: $this->overrideString($overrides, 'supportEmail', $this->supportEmail),
            statusPageUrl: $this->overrideString($overrides, 'statusPageUrl', $this->statusPageUrl),
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

    /**
     * @param  array<array-key, mixed>  $overrides
     */
    private function overrideString(array $overrides, string $key, ?string $current): ?string
    {
        $value = $overrides[$key] ?? null;

        return is_string($value) ? $value : $current;
    }

    /**
     * @param  array<array-key, mixed>  $overrides
     */
    private function overrideInt(array $overrides, string $key, int $current): int
    {
        $value = $overrides[$key] ?? null;

        return is_int($value) ? $value : $current;
    }

    /**
     * @param  array<array-key, mixed>  $overrides
     */
    private function overrideBool(array $overrides, string $key, bool $current): bool
    {
        $value = $overrides[$key] ?? null;

        return is_bool($value) ? $value : $current;
    }
}
