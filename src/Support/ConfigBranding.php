<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Support;

use FreshwaveOnline\Janitor\Contracts\BrandingResolver;
use FreshwaveOnline\Janitor\Data\Branding;
use FreshwaveOnline\Janitor\Integrations\Filament;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The default branding source: this package's config, with Filament's active
 * panel filling the gaps when it is installed.
 *
 * Explicit config always wins over anything Filament reports — you configured it
 * on purpose.
 */
class ConfigBranding implements BrandingResolver
{
    public function __construct(protected readonly Config $config) {}

    public function resolve(Request $request, int $statusCode): Branding
    {
        return new Branding(
            name: $this->name($request),
            logo: $this->logo($request),
            logoDark: Url::asset($this->string('brand.logo_dark')),
            logoHeight: $this->integer('brand.logo_height', 32),
            showNameBesideLogo: $this->setting('brand.show_name_beside_logo') === true,
            primaryColor: $this->primaryColor($request),
            primaryColorLight: $this->string('colors.light'),
            primaryColorDark: $this->string('colors.dark'),
            autoContrast: $this->setting('colors.auto_contrast') !== false,
            homeUrl: $this->homeUrl($request),
            loginUrl: $this->loginUrl($request),
            supportEmail: $this->supportEmail($statusCode),
            statusPageUrl: Url::link($this->string('links.status_page')),
        );
    }

    protected function name(Request $request): ?string
    {
        $configured = $this->string('brand.name');

        if ($configured !== null) {
            return $configured;
        }

        if ($this->inheritsFromFilament('brand_name', $request)) {
            $name = Filament::brandName($request);

            if ($name !== null) {
                return $name;
            }
        }

        $appName = $this->config->get('app.name');

        return is_string($appName) && $appName !== '' ? $appName : null;
    }

    protected function logo(Request $request): ?string
    {
        return Url::asset($this->string('brand.logo')
            ?? ($this->inheritsFromFilament('brand_logo', $request) ? Filament::brandLogo($request) : null));
    }

    protected function primaryColor(Request $request): ?string
    {
        if ($this->inheritsFromFilament('primary_color', $request)) {
            $shade = $this->integer('filament.color_shade', 600);
            $panelColor = Filament::primaryColor($request, $shade);

            if ($panelColor !== null) {
                return $panelColor;
            }
        }

        return $this->string('colors.primary');
    }

    protected function homeUrl(Request $request): ?string
    {
        $explicit = Url::link($this->string('links.home'));

        if ($explicit !== null) {
            return $explicit;
        }

        $fromRoute = $this->route($this->string('links.home_route'));

        if ($fromRoute !== null) {
            return $fromRoute;
        }

        if ($this->inheritsFromFilament('home_url', $request)) {
            $url = Url::link(Filament::homeUrl($request));

            if ($url !== null) {
                return $url;
            }
        }

        // Without a working URL generator, a relative link still gets the
        // visitor home.
        return Guard::value(static fn (): string => url('/'), '/');
    }

    protected function loginUrl(Request $request): ?string
    {
        if ($this->inheritsFromFilament('login_url', $request)) {
            $url = Url::link(Filament::loginUrl($request));

            if ($url !== null) {
                return $url;
            }
        }

        return $this->route($this->string('links.login_route'));
    }

    /**
     * Offering "contact support" on a mistyped URL is noise; the config decides
     * which status codes deserve it.
     */
    protected function supportEmail(int $statusCode): ?string
    {
        $email = $this->string('links.support_email');

        if ($email === null) {
            return null;
        }

        /** @var list<int>|null $codes */
        $codes = $this->setting('links.support_email_codes');

        return $codes === null || in_array($statusCode, $codes, true) ? $email : null;
    }

    protected function inheritsFromFilament(string $feature, Request $request): bool
    {
        if (! Filament::installed() || $this->setting('filament.enabled') !== true) {
            return false;
        }

        if ($this->setting('filament.inherit.'.$feature) !== true) {
            return false;
        }

        if ($this->setting('filament.only_on_panel_routes') === true) {
            return Filament::isPanelRequest($request);
        }

        return true;
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->config->get('janitor.'.$key, $default);
    }

    protected function string(string $key): ?string
    {
        $value = $this->setting($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    protected function integer(string $key, int $default): int
    {
        $value = $this->setting($key);

        return is_int($value) ? $value : $default;
    }

    /**
     * Resolve a named route, or null when the name is unset, unknown, or the
     * router cannot answer at all.
     */
    protected function route(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return Guard::value(static function () use ($name): ?string {
            if (! Route::has($name)) {
                return null;
            }

            return Url::link(route($name));
        });
    }
}
