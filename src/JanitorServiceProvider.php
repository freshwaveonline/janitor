<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor;

use FreshwaveOnline\Janitor\Console\InstallCommand;
use FreshwaveOnline\Janitor\Contracts\ActionResolver;
use FreshwaveOnline\Janitor\Contracts\BrandingResolver;
use FreshwaveOnline\Janitor\Contracts\ErrorContextBuilder;
use FreshwaveOnline\Janitor\Contracts\ErrorRenderer;
use FreshwaveOnline\Janitor\Contracts\MessageNumberGenerator;
use FreshwaveOnline\Janitor\Contracts\RequestIdResolver;
use FreshwaveOnline\Janitor\Contracts\RetryAfterResolver;
use FreshwaveOnline\Janitor\Http\Controllers\PreviewController;
use FreshwaveOnline\Janitor\Http\Middleware\AssignRequestId;
use FreshwaveOnline\Janitor\Http\Middleware\InjectJanitorAssets;
use FreshwaveOnline\Janitor\Support\ActionFactory;
use FreshwaveOnline\Janitor\Support\ConfigBranding;
use FreshwaveOnline\Janitor\Support\Guard;
use FreshwaveOnline\Janitor\Support\MessageNumber;
use FreshwaveOnline\Janitor\Support\RequestId;
use FreshwaveOnline\Janitor\Support\RetryAfter;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class JanitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/janitor.php', 'janitor');

        // Everything is bound by contract. Rebinding any one of these in your
        // own AppServiceProvider replaces that piece and leaves the rest alone:
        //
        //     $this->app->bind(MessageNumberGenerator::class, MyIncidentCodes::class);
        //     $this->app->bind(BrandingResolver::class, TenantBranding::class);
        //
        // The concrete classes stay bound under their own names too, so you can
        // decorate the default rather than replace it.
        $this->app->singleton(MessageNumber::class, function (Application $app): MessageNumber {
            /** @phpstan-ignore-next-line argument.type */
            return new MessageNumber(self::sectionOf($app, 'message_number'), $app->basePath());
        });

        $this->app->singleton(RequestId::class, function (Application $app): RequestId {
            /** @phpstan-ignore-next-line argument.type */
            return new RequestId(self::sectionOf($app, 'request_id'));
        });

        $this->app->singleton(RetryAfter::class, function (Application $app): RetryAfter {
            /** @phpstan-ignore-next-line argument.type */
            return new RetryAfter(self::sectionOf($app, 'retry_after'));
        });

        $this->app->singleton(ConfigBranding::class);
        $this->app->singleton(ActionFactory::class);
        $this->app->singleton(ErrorContextFactory::class);
        $this->app->singleton(ErrorPageRenderer::class);

        $this->app->bind(MessageNumberGenerator::class, MessageNumber::class);
        $this->app->bind(RequestIdResolver::class, RequestId::class);
        $this->app->bind(RetryAfterResolver::class, RetryAfter::class);
        $this->app->bind(BrandingResolver::class, ConfigBranding::class);
        $this->app->bind(ActionResolver::class, ActionFactory::class);
        $this->app->bind(ErrorContextBuilder::class, ErrorContextFactory::class);
        $this->app->bind(ErrorRenderer::class, ErrorPageRenderer::class);

        $this->app->alias(ErrorPageRenderer::class, 'janitor');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'janitor');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'janitor');

        $this->registerPublishing();
        $this->registerBladeDirectives();
        $this->registerExceptionHandling();
        $this->registerMiddleware();
        $this->registerPreviewRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/janitor.php' => $this->app->configPath('janitor.php'),
        ], ['janitor', 'janitor-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/janitor'),
        ], ['janitor', 'janitor-views']);

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/janitor'),
        ], ['janitor', 'janitor-lang', 'janitor-translations']);
    }

    /**
     * Hook into Laravel's exception handler.
     *
     * A `renderable` callback that returns null hands the exception straight
     * back to the framework, which is exactly the fallback we want whenever the
     * package decides not to handle something.
     */
    protected function registerExceptionHandling(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (method_exists($handler, 'renderable')) {
            $handler->renderable(function (Throwable $exception, Request $request): ?SymfonyResponse {
                // Resolving the renderer touches the container and the config.
                // If that fails, Laravel's own handler still works — returning
                // null is what hands the exception back to it.
                return Guard::value(
                    fn (): ?SymfonyResponse => $this->app->make(ErrorRenderer::class)->render($request, $exception),
                );
            });
        }

        // Put the message number in the log context so the code on screen can be
        // grepped straight out of the log for that exception.
        if (method_exists($handler, 'buildContextUsing')
            && $this->app->make(Config::class)->get('janitor.message_number.log_context') === true) {
            $handler->buildContextUsing(function (Throwable $exception): array {
                $context = [];

                try {
                    $numbers = $this->app->make(MessageNumberGenerator::class);
                    $status = $this->app->make(ErrorRenderer::class)->statusFor($exception);

                    $number = $numbers->for($exception, $status);

                    if ($number !== null) {
                        $context['message_number'] = $number;
                    }

                    if ($this->app->bound('request')) {
                        $requestId = $this->app->make(RequestIdResolver::class)->resolve($this->app->make('request'));

                        if ($requestId !== null) {
                            $context['request_id'] = $requestId;
                        }
                    }
                } catch (Throwable) {
                    // Logging must never be the thing that breaks.
                }

                return $context;
            });
        }
    }

    protected function registerMiddleware(): void
    {
        $config = $this->app->make(Config::class);

        if ($config->get('janitor.request_id.middleware') === true) {
            $this->pushGlobalMiddleware(AssignRequestId::class);
        }

        if ($config->get('janitor.livewire.inject_assets') === true && class_exists(Livewire::class)) {
            $this->pushGlobalMiddleware(InjectJanitorAssets::class);
        }
    }

    /**
     * @param  class-string  $middleware
     */
    protected function pushGlobalMiddleware(string $middleware): void
    {
        try {
            $kernel = $this->app->make(HttpKernel::class);

            if (method_exists($kernel, 'hasMiddleware') && $kernel->hasMiddleware($middleware)) {
                return;
            }

            if (method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware($middleware);
            }
        } catch (Throwable) {
            // Console kernels and non-HTTP contexts have no middleware stack.
        }
    }

    protected function registerPreviewRoutes(): void
    {
        if (! $this->previewEnabled()) {
            return;
        }

        $config = $this->app->make(Config::class);
        $configured = $config->get('janitor.preview.path', '_janitor');
        $path = trim(is_string($configured) ? $configured : '_janitor', '/');

        /** @var list<string> $middleware */
        $middleware = $config->get('janitor.preview.middleware', ['web']);

        Route::middleware($middleware)->group(function () use ($path): void {
            Route::get($path, [PreviewController::class, 'index'])->name('janitor.preview.index');
            Route::get($path.'/{code}', [PreviewController::class, 'show'])
                ->whereNumber('code')
                ->name('janitor.preview.show');
        });
    }

    protected function previewEnabled(): bool
    {
        $setting = $this->app->make(Config::class)->get('janitor.preview.enabled');

        // `null` means "local only" — the safe default for a route that renders
        // stack traces on demand.
        return $setting === null
            ? $this->app->environment('local')
            : $setting === true;
    }

    /**
     * Read one `janitor.*` sub-array, tolerating a config repository that has
     * been replaced or emptied.
     *
     * @return array<string, mixed>
     */
    protected static function sectionOf(Application $app, string $key): array
    {
        $config = $app->make(Config::class)->get('janitor.'.$key, []);

        /** @var array<string, mixed> */
        return is_array($config) ? $config : [];
    }

    protected function registerBladeDirectives(): void
    {
        Blade::directive('janitorScripts', static function (): string {
            return "<?php echo view('janitor::partials.livewire-script')->render(); ?>";
        });

        // `@janitorIcon('home', ['class' => 'jn-icon'])`
        Blade::directive('janitorIcon', static function (string $expression): string {
            return "<?php echo \\FreshwaveOnline\\Janitor\\Support\\Icons::svg({$expression}); ?>";
        });
    }
}
