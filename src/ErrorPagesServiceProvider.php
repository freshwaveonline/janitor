<?php

declare(strict_types=1);

namespace Vvdboogaard\ErrorPages;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;
use Vvdboogaard\ErrorPages\Console\InstallCommand;
use Vvdboogaard\ErrorPages\Contracts\ActionResolver;
use Vvdboogaard\ErrorPages\Contracts\BrandingResolver;
use Vvdboogaard\ErrorPages\Contracts\ErrorContextBuilder;
use Vvdboogaard\ErrorPages\Contracts\ErrorRenderer;
use Vvdboogaard\ErrorPages\Contracts\MessageNumberGenerator;
use Vvdboogaard\ErrorPages\Contracts\RequestIdResolver;
use Vvdboogaard\ErrorPages\Contracts\RetryAfterResolver;
use Vvdboogaard\ErrorPages\Http\Controllers\PreviewController;
use Vvdboogaard\ErrorPages\Http\Middleware\AssignRequestId;
use Vvdboogaard\ErrorPages\Http\Middleware\InjectErrorPagesAssets;
use Vvdboogaard\ErrorPages\Support\ActionFactory;
use Vvdboogaard\ErrorPages\Support\ConfigBranding;
use Vvdboogaard\ErrorPages\Support\MessageNumber;
use Vvdboogaard\ErrorPages\Support\RequestId;
use Vvdboogaard\ErrorPages\Support\RetryAfter;

class ErrorPagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/error-pages.php', 'error-pages');

        // Everything is bound by contract. Rebinding any one of these in your
        // own AppServiceProvider replaces that piece and leaves the rest alone:
        //
        //     $this->app->bind(MessageNumberGenerator::class, MyIncidentCodes::class);
        //     $this->app->bind(BrandingResolver::class, TenantBranding::class);
        //
        // The concrete classes stay bound under their own names too, so you can
        // decorate the default rather than replace it.
        $this->app->singleton(MessageNumber::class, function ($app): MessageNumber {
            /** @var array<string, mixed> $config */
            $config = $app->make(Config::class)->get('error-pages.message_number', []);

            /** @phpstan-ignore-next-line argument.type */
            return new MessageNumber($config, $app->basePath());
        });

        $this->app->singleton(RequestId::class, function ($app): RequestId {
            /** @var array<string, mixed> $config */
            $config = $app->make(Config::class)->get('error-pages.request_id', []);

            /** @phpstan-ignore-next-line argument.type */
            return new RequestId($config);
        });

        $this->app->singleton(RetryAfter::class, function ($app): RetryAfter {
            /** @var array<string, mixed> $config */
            $config = $app->make(Config::class)->get('error-pages.retry_after', []);

            /** @phpstan-ignore-next-line argument.type */
            return new RetryAfter($config);
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

        $this->app->alias(ErrorPageRenderer::class, 'error-pages');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'error-pages');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'error-pages');

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
            __DIR__.'/../config/error-pages.php' => $this->app->configPath('error-pages.php'),
        ], ['error-pages', 'error-pages-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/error-pages'),
        ], ['error-pages', 'error-pages-views']);

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/error-pages'),
        ], ['error-pages', 'error-pages-lang', 'error-pages-translations']);
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
                return $this->app->make(ErrorRenderer::class)->render($request, $exception);
            });
        }

        // Put the message number in the log context so the code on screen can be
        // grepped straight out of the log for that exception.
        if (method_exists($handler, 'buildContextUsing')
            && $this->app->make(Config::class)->get('error-pages.message_number.log_context') === true) {
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

        if ($config->get('error-pages.request_id.middleware') === true) {
            $this->pushGlobalMiddleware(AssignRequestId::class);
        }

        if ($config->get('error-pages.livewire.inject_assets') === true && class_exists(Livewire::class)) {
            $this->pushGlobalMiddleware(InjectErrorPagesAssets::class);
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
        $path = trim((string) $config->get('error-pages.preview.path', '_error-pages'), '/');

        /** @var list<string> $middleware */
        $middleware = $config->get('error-pages.preview.middleware', ['web']);

        Route::middleware($middleware)->group(function () use ($path): void {
            Route::get($path, [PreviewController::class, 'index'])->name('error-pages.preview.index');
            Route::get($path.'/{code}', [PreviewController::class, 'show'])
                ->whereNumber('code')
                ->name('error-pages.preview.show');
        });
    }

    protected function previewEnabled(): bool
    {
        $setting = $this->app->make(Config::class)->get('error-pages.preview.enabled');

        // `null` means "local only" — the safe default for a route that renders
        // stack traces on demand.
        return $setting === null
            ? $this->app->environment('local')
            : $setting === true;
    }

    protected function registerBladeDirectives(): void
    {
        Blade::directive('errorPagesScripts', static function (): string {
            return "<?php echo view('error-pages::partials.livewire-script')->render(); ?>";
        });

        // `@errorPagesIcon('home', ['class' => 'ep-icon'])`
        Blade::directive('errorPagesIcon', static function (string $expression): string {
            return "<?php echo \\Vvdboogaard\\ErrorPages\\Support\\Icons::svg({$expression}); ?>";
        });
    }
}
