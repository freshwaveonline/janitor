<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Vvdboogaard\ErrorPages\Contracts\ActionResolver;
use Vvdboogaard\ErrorPages\Data\ErrorAction;
use Vvdboogaard\ErrorPages\Data\ErrorContext;

/**
 * Builds the call-to-action buttons for a status code from config.
 *
 * An action that cannot resolve — no support address configured, no `login`
 * route, no retry moment — removes itself rather than rendering a dead button.
 */
class ActionFactory implements ActionResolver
{
    /**
     * Default appearance per built-in action, before the "exactly one primary"
     * promotion runs.
     *
     * @var array<string, array{icon: string, style: string, behaviour: string}>
     */
    protected const BUILT_INS = [
        'home' => ['icon' => 'home', 'style' => ErrorAction::STYLE_PRIMARY, 'behaviour' => 'link'],
        'back' => ['icon' => 'arrow-left', 'style' => ErrorAction::STYLE_SECONDARY, 'behaviour' => 'back'],
        'reload' => ['icon' => 'arrow-path', 'style' => ErrorAction::STYLE_PRIMARY, 'behaviour' => 'reload'],
        'retry' => ['icon' => 'clock', 'style' => ErrorAction::STYLE_PRIMARY, 'behaviour' => 'reload'],
        'login' => ['icon' => 'key', 'style' => ErrorAction::STYLE_PRIMARY, 'behaviour' => 'link'],
        'support' => ['icon' => 'envelope', 'style' => ErrorAction::STYLE_GHOST, 'behaviour' => 'link'],
        'status_page' => ['icon' => 'globe-alt', 'style' => ErrorAction::STYLE_GHOST, 'behaviour' => 'link'],
    ];

    public function __construct(
        protected readonly Config $config,
        protected readonly Translator $translator,
    ) {}

    /**
     * @return list<ErrorAction>
     */
    public function for(ErrorContext $context, Request $request): array
    {
        $actions = [];

        foreach ($this->definitionsFor($context->statusCode) as $definition) {
            $action = is_array($definition)
                ? $this->custom($definition)
                : $this->builtIn((string) $definition, $context);

            if ($action !== null) {
                $actions[$action->key] = $action;
            }
        }

        return $this->promotePrimary(array_values($actions));
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    protected function definitionsFor(int $statusCode): array
    {
        /** @var array<array-key, mixed> $map */
        $map = $this->setting('actions') ?? [];

        $definitions = $map[$statusCode] ?? $map['default'] ?? ['back', 'home'];

        /** @var list<string|array<string, mixed>> $definitions */
        return is_array($definitions) ? array_values($definitions) : [];
    }

    protected function builtIn(string $key, ErrorContext $context): ?ErrorAction
    {
        $preset = static::BUILT_INS[$key] ?? null;

        if ($preset === null) {
            return null;
        }

        $branding = $context->branding;
        $external = false;

        $url = match ($key) {
            'home' => $branding->homeUrl,
            'login' => $branding->loginUrl,
            'support' => $context->supportMailto($this->string('links.support_subject')),
            'status_page' => $branding->statusPageUrl,
            default => null,
        };

        // Link-style actions with nothing to link to are dead buttons.
        if ($preset['behaviour'] === 'link' && $url === null) {
            return null;
        }

        if ($key === 'status_page') {
            $external = true;
        }

        // Without a retry moment this is just a reload button with the wrong
        // label; the configured 'reload' action covers that case.
        if ($key === 'retry' && ! $context->hasRetry()) {
            return null;
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
    protected function custom(array $definition): ?ErrorAction
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
        if (is_string($url) && ! str_contains($url, '/') && Route::has($url)) {
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
            external: ($definition['external'] ?? false) === true,
        );
    }

    /**
     * Guarantee exactly one visually dominant button: the config decides the
     * order, the first candidate wins the emphasis.
     *
     * @param  list<ErrorAction>  $actions
     * @return list<ErrorAction>
     */
    protected function promotePrimary(array $actions): array
    {
        if ($actions === []) {
            return $actions;
        }

        $primaryIndex = null;

        foreach ($actions as $index => $action) {
            if ($action->isPrimary() && $primaryIndex === null) {
                $primaryIndex = $index;
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

    protected function label(string $key): string
    {
        return (string) $this->translator->get('error-pages::ui.actions.'.$key);
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->config->get('error-pages.'.$key, $default);
    }

    protected function string(string $key): ?string
    {
        $value = $this->setting($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
