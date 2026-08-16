<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Support;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Vvdboogaard\ErrorPages\Data\ErrorAction;
use Vvdboogaard\ErrorPages\Integrations\Filament;

/**
 * Builds the call-to-action buttons for a status code.
 *
 * An action that cannot resolve — no support address configured, no `login`
 * route, no retry moment — removes itself rather than rendering a dead button.
 */
final class ActionFactory
{
    /**
     * Default appearance per built-in action, before the "first one is primary"
     * promotion runs.
     *
     * @var array<string, array{icon: string, style: string, behaviour: string}>
     */
    private const BUILT_INS = [
        'home' => ['icon' => 'home', 'style' => ErrorAction::STYLE_PRIMARY, 'behaviour' => 'link'],
        'back' => ['icon' => 'arrow-left', 'style' => ErrorAction::STYLE_SECONDARY, 'behaviour' => 'back'],
        'reload' => ['icon' => 'arrow-path', 'style' => ErrorAction::STYLE_PRIMARY, 'behaviour' => 'reload'],
        'retry' => ['icon' => 'clock', 'style' => ErrorAction::STYLE_PRIMARY, 'behaviour' => 'reload'],
        'login' => ['icon' => 'key', 'style' => ErrorAction::STYLE_PRIMARY, 'behaviour' => 'link'],
        'support' => ['icon' => 'envelope', 'style' => ErrorAction::STYLE_GHOST, 'behaviour' => 'link'],
        'status_page' => ['icon' => 'globe-alt', 'style' => ErrorAction::STYLE_GHOST, 'behaviour' => 'link'],
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly Translator $translator,
    ) {}

    /**
     * @param  array{home_url?: string|null, login_url?: string|null, support_mailto?: string|null, has_retry?: bool}  $resolved
     * @return list<ErrorAction>
     */
    public function for(int $statusCode, array $resolved, ?Request $request = null): array
    {
        $actions = [];

        foreach ($this->keysFor($statusCode) as $definition) {
            $action = is_array($definition)
                ? $this->custom($definition)
                : $this->builtIn((string) $definition, $resolved, $request);

            if ($action !== null) {
                $actions[$action->key] = $action;
            }
        }

        return $this->promotePrimary(array_values($actions));
    }

    /**
     * Resolve the URLs the built-in actions depend on.
     *
     * @param  array<string, mixed>  $config
     * @return array{home_url: string|null, login_url: string|null}
     */
    public static function resolveUrls(array $config, ?Request $request = null): array
    {
        return [
            'home_url' => self::resolveHomeUrl($config, $request),
            'login_url' => self::resolveLoginUrl($config, $request),
        ];
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private function keysFor(int $statusCode): array
    {
        /** @var array<array-key, mixed> $map */
        $map = $this->config['actions'] ?? [];

        $definitions = $map[$statusCode] ?? $map['default'] ?? ['back', 'home'];

        /** @var list<string|array<string, mixed>> $definitions */
        return is_array($definitions) ? array_values($definitions) : [];
    }

    /**
     * @param  array{home_url?: string|null, login_url?: string|null, support_mailto?: string|null, has_retry?: bool}  $resolved
     */
    private function builtIn(string $key, array $resolved, ?Request $request): ?ErrorAction
    {
        $preset = self::BUILT_INS[$key] ?? null;

        if ($preset === null) {
            return null;
        }

        $url = null;
        $external = false;

        switch ($key) {
            case 'home':
                $url = $resolved['home_url'] ?? null;

                if ($url === null) {
                    return null;
                }

                break;

            case 'login':
                $url = $resolved['login_url'] ?? null;

                if ($url === null) {
                    return null;
                }

                break;

            case 'support':
                $url = $resolved['support_mailto'] ?? null;

                if ($url === null) {
                    return null;
                }

                break;

            case 'status_page':
                $url = $this->stringConfig('links.status_page');

                if ($url === null) {
                    return null;
                }

                $external = true;

                break;

            case 'retry':
                // Without a retry moment this is just a reload button with the
                // wrong label; let the configured 'reload' action cover that.
                if (($resolved['has_retry'] ?? false) === false) {
                    return null;
                }

                break;
        }

        return new ErrorAction(
            key: $key,
            label: $this->label($key),
            url: $url,
            icon: $preset['icon'],
            style: $preset['style'],
            behaviour: $preset['behaviour'],
            external: $external,
            waitsForRetry: $key === 'retry',
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function custom(array $definition): ?ErrorAction
    {
        $label = $definition['label'] ?? null;

        if (! is_string($label) || $label === '') {
            return null;
        }

        $url = $definition['url'] ?? null;
        $behaviour = is_string($definition['behaviour'] ?? null) ? (string) $definition['behaviour'] : 'link';

        if ($behaviour === 'link' && ! is_string($url)) {
            return null;
        }

        // Route names are more portable than hard-coded URLs in a config file.
        if (is_string($url) && ! str_contains($url, '/') && $this->router()->has($url)) {
            $url = route($url);
        }

        $icon = $definition['icon'] ?? null;
        $style = $definition['style'] ?? ErrorAction::STYLE_SECONDARY;

        return new ErrorAction(
            key: is_string($definition['key'] ?? null) ? (string) $definition['key'] : 'custom-'.substr(md5($label), 0, 6),
            label: $this->translator->has($label) ? (string) $this->translator->get($label) : $label,
            url: is_string($url) ? $url : null,
            icon: is_string($icon) && Icons::exists($icon) ? $icon : null,
            style: in_array($style, [ErrorAction::STYLE_PRIMARY, ErrorAction::STYLE_SECONDARY, ErrorAction::STYLE_GHOST], true)
                ? (string) $style
                : ErrorAction::STYLE_SECONDARY,
            behaviour: $behaviour,
            external: (bool) ($definition['external'] ?? false),
        );
    }

    /**
     * Guarantee exactly one visually dominant button: the config decides the
     * order, the first candidate wins the emphasis.
     *
     * @param  list<ErrorAction>  $actions
     * @return list<ErrorAction>
     */
    private function promotePrimary(array $actions): array
    {
        if ($actions === []) {
            return $actions;
        }

        $primaryIndex = null;

        foreach ($actions as $index => $action) {
            if ($action->isPrimary()) {
                $primaryIndex = $primaryIndex ?? $index;
            }
        }

        return array_values(array_map(
            static function (ErrorAction $action, int $index) use ($primaryIndex): ErrorAction {
                $shouldBePrimary = $primaryIndex === null ? $index === 0 : $index === $primaryIndex;

                if ($shouldBePrimary === $action->isPrimary()) {
                    return $action;
                }

                return new ErrorAction(
                    key: $action->key,
                    label: $action->label,
                    url: $action->url,
                    icon: $action->icon,
                    style: $shouldBePrimary ? ErrorAction::STYLE_PRIMARY : ErrorAction::STYLE_SECONDARY,
                    behaviour: $action->behaviour,
                    external: $action->external,
                    description: $action->description,
                    waitsForRetry: $action->waitsForRetry,
                );
            },
            $actions,
            array_keys($actions),
        ));
    }

    private function label(string $key): string
    {
        return (string) $this->translator->get('error-pages::ui.actions.'.$key);
    }

    private function stringConfig(string $path): ?string
    {
        $value = data_get($this->config, $path);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function router(): Router
    {
        return Route::getFacadeRoot();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function resolveHomeUrl(array $config, ?Request $request): ?string
    {
        $explicit = data_get($config, 'links.home');

        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $routeName = data_get($config, 'links.home_route');

        if (is_string($routeName) && $routeName !== '' && Route::has($routeName)) {
            return route($routeName);
        }

        if (self::filamentEnabled($config, $request, 'home_url')) {
            $url = Filament::homeUrl($request);

            if ($url !== null) {
                return $url;
            }
        }

        return url('/');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function resolveLoginUrl(array $config, ?Request $request): ?string
    {
        if (self::filamentEnabled($config, $request, 'login_url')) {
            $url = Filament::loginUrl($request);

            if ($url !== null) {
                return $url;
            }
        }

        $routeName = data_get($config, 'links.login_route');

        if (is_string($routeName) && $routeName !== '' && Route::has($routeName)) {
            return route($routeName);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function filamentEnabled(array $config, ?Request $request, string $feature): bool
    {
        if (! Filament::installed() || data_get($config, 'filament.enabled') !== true) {
            return false;
        }

        if (data_get($config, 'filament.inherit.'.$feature) !== true) {
            return false;
        }

        if (data_get($config, 'filament.only_on_panel_routes') === true) {
            return $request !== null && Filament::isPanelRequest($request);
        }

        return true;
    }
}
