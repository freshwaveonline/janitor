<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vvdboogaard\ErrorPages\Contracts\ActionResolver;
use Vvdboogaard\ErrorPages\Contracts\BrandingResolver;
use Vvdboogaard\ErrorPages\Contracts\ErrorContextBuilder;
use Vvdboogaard\ErrorPages\Contracts\ErrorRenderer;
use Vvdboogaard\ErrorPages\Contracts\MessageNumberGenerator;
use Vvdboogaard\ErrorPages\Contracts\RequestIdResolver;
use Vvdboogaard\ErrorPages\Contracts\RetryAfterResolver;
use Vvdboogaard\ErrorPages\Data\Branding;
use Vvdboogaard\ErrorPages\Data\ErrorAction;
use Vvdboogaard\ErrorPages\Data\ErrorContext;
use Vvdboogaard\ErrorPages\ErrorContextFactory;
use Vvdboogaard\ErrorPages\ErrorPageRenderer;
use Vvdboogaard\ErrorPages\Support\ActionFactory;
use Vvdboogaard\ErrorPages\Support\ConfigBranding;
use Vvdboogaard\ErrorPages\Support\Icons;
use Vvdboogaard\ErrorPages\Support\MessageNumber;
use Vvdboogaard\ErrorPages\Support\RequestId;
use Vvdboogaard\ErrorPages\Support\RetryAfter;

/*
|--------------------------------------------------------------------------
| Every moving part is swappable
|--------------------------------------------------------------------------
|
| These tests are the promise: bind your own implementation of any contract
| and the rest of the package keeps working around it.
|
*/

beforeEach(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/missing', fn () => throw new NotFoundHttpException);
        Route::get('/boom', fn () => throw new RuntimeException('kaboom'));
    });
});

it('binds every contract to a default implementation', function (string $contract, string $default): void {
    expect($this->app->make($contract))->toBeInstanceOf($default);
})->with([
    [MessageNumberGenerator::class, MessageNumber::class],
    [RequestIdResolver::class, RequestId::class],
    [RetryAfterResolver::class, RetryAfter::class],
    [BrandingResolver::class, ConfigBranding::class],
    [ActionResolver::class, ActionFactory::class],
    [ErrorContextBuilder::class, ErrorContextFactory::class],
    [ErrorRenderer::class, ErrorPageRenderer::class],
]);

it('uses a custom message number generator', function (): void {
    $this->app->bind(MessageNumberGenerator::class, fn (): MessageNumberGenerator => new class implements MessageNumberGenerator
    {
        public function for(?Throwable $exception, int $statusCode): ?string
        {
            return 'INC-'.$statusCode;
        }

        public function fingerprint(?Throwable $exception, int $statusCode): string
        {
            return 'incident:'.$statusCode;
        }
    });

    $this->app->forgetInstance(ErrorContextFactory::class);

    $response = $this->get('/missing');

    $response->assertSee('INC-404')->assertHeader('X-Message-Number', 'INC-404');
});

it('uses a custom request id resolver', function (): void {
    $this->app->bind(RequestIdResolver::class, fn (): RequestIdResolver => new class implements RequestIdResolver
    {
        public function resolve(Request $request): ?string
        {
            return 'trace-from-apm';
        }

        public function responseHeader(): ?string
        {
            return 'X-Trace';
        }
    });

    $this->app->forgetInstance(ErrorContextFactory::class);

    $this->get('/missing')
        ->assertSee('trace-from-apm')
        ->assertHeader('X-Trace', 'trace-from-apm');
});

it('uses a custom retry-after resolver', function (): void {
    CarbonImmutable::setTestNow('2026-08-16 12:00:00');

    $this->app->bind(RetryAfterResolver::class, fn (): RetryAfterResolver => new class implements RetryAfterResolver
    {
        public function resolve(?Throwable $exception, ?Request $request = null): ?CarbonInterface
        {
            // A deploy window this package could never have known about.
            return CarbonImmutable::parse('2026-08-16 12:30:00');
        }
    });

    $this->app->forgetInstance(ErrorContextFactory::class);

    $this->get('/missing')
        ->assertSee('When to try again')
        ->assertSee('12:30');

    CarbonImmutable::setTestNow();
});

it('uses a custom branding resolver, the multi-tenant case', function (): void {
    $this->app->bind(BrandingResolver::class, fn (): BrandingResolver => new class implements BrandingResolver
    {
        public function resolve(Request $request, int $statusCode): Branding
        {
            return new Branding(
                name: 'Tenant B.V.',
                primaryColor: '#b91c1c',
                autoContrast: false,
                homeUrl: 'https://tenant.test',
                supportEmail: 'help@tenant.test',
            );
        }
    });

    $this->app->forgetInstance(ErrorContextFactory::class);

    $this->get('/missing')
        ->assertSee('Tenant B.V.')
        ->assertSee('--ep-primary: #b91c1c', false)
        ->assertSee('https://tenant.test', false)
        ->assertSee('help@tenant.test');
});

it('lets a branding resolver decorate the default instead of replacing it', function (): void {
    config()->set('error-pages.brand.name', 'Acme');

    $this->app->bind(BrandingResolver::class, fn ($app): BrandingResolver => new class($app->make(ConfigBranding::class)) implements BrandingResolver
    {
        public function __construct(private readonly ConfigBranding $inner) {}

        public function resolve(Request $request, int $statusCode): Branding
        {
            // Keep everything the config decided; override one value.
            return $this->inner->resolve($request, $statusCode)->with(primaryColor: '#0ea5e9');
        }
    });

    $this->app->forgetInstance(ErrorContextFactory::class);

    $this->get('/missing')
        ->assertSee('Acme')
        ->assertSee('--ep-primary: #0ea5e9', false);
});

it('uses a custom action resolver for runtime-dependent buttons', function (): void {
    $this->app->bind(ActionResolver::class, fn (): ActionResolver => new class implements ActionResolver
    {
        public function for(ErrorContext $context, Request $request): array
        {
            return [
                new ErrorAction(
                    key: 'resume',
                    label: 'Resume your checkout',
                    url: '/cart/9f3a',
                    icon: 'arrow-path',
                    style: ErrorAction::STYLE_PRIMARY,
                ),
            ];
        }
    });

    $this->app->forgetInstance(ErrorContextFactory::class);

    $this->get('/missing')
        ->assertSee('Resume your checkout')
        ->assertSee('href="/cart/9f3a"', false)
        ->assertDontSee('data-ep-action="back"', false);
});

it('lets a subclass of the factory change where the copy comes from', function (): void {
    $this->app->bind(ErrorContextBuilder::class, fn ($app) => new class($app, $app->make(Repository::class), $app->make(Translator::class), $app->make(MessageNumberGenerator::class), $app->make(RequestIdResolver::class), $app->make(RetryAfterResolver::class), $app->make(BrandingResolver::class), $app->make(ActionResolver::class)) extends ErrorContextFactory
    {
        // Every method on the factory is protected precisely so this works.
        protected function optionalLine(int $statusCode, string $key, ?string $messageNumber, Branding $branding): ?string
        {
            return $key === 'title' ? 'Copy from our CMS' : parent::optionalLine($statusCode, $key, $messageNumber, $branding);
        }
    });

    $this->get('/missing')->assertSee('Copy from our CMS');
});

it('lets a custom renderer decide differently', function (): void {
    $this->app->bind(ErrorRenderer::class, fn ($app) => new class($app, $app->make(Repository::class), $app->make(Factory::class), $app->make(ErrorContextBuilder::class)) extends ErrorPageRenderer
    {
        public function shouldHandle(Request $request, Throwable $exception): bool
        {
            // Hand 404s back to Laravel, keep the rest.
            return $this->statusFor($exception) !== 404 && parent::shouldHandle($request, $exception);
        }
    });

    $this->get('/missing')->assertDontSee('What you can do');
    $this->get('/boom')->assertSee('What you can do');
});

/*
|--------------------------------------------------------------------------
| Icons
|--------------------------------------------------------------------------
*/

it('renders a registered custom icon', function (): void {
    Icons::register('acme-mark', 'M12 2 2 22h20L12 2z');

    expect(Icons::exists('acme-mark'))->toBeTrue()
        ->and(Icons::names())->toContain('acme-mark')
        ->and(Icons::svg('acme-mark')->toHtml())->toContain('M12 2 2 22h20L12 2z');
});

it('lets a registered icon replace a bundled one', function (): void {
    Icons::register('home', 'M0 0h24v24H0z');

    expect(Icons::path('home'))->toBe('M0 0h24v24H0z');
});

it('uses a registered icon for a status code', function (): void {
    Icons::register('acme-lost', 'M1 1h22v22H1z');
    Icons::useForStatus(404, 'acme-lost');

    expect(Icons::forStatus(404))->toBe('acme-lost');

    $this->get('/missing')->assertSee('M1 1h22v22H1z', false);
});

it('ignores a status override pointing at an icon that does not exist', function (): void {
    Icons::useForStatus(404, 'never-registered');

    expect(Icons::forStatus(404))->toBe('magnifying-glass');
});

it('lets a custom icon be used by a config action', function (): void {
    Icons::register('sparkles', 'M5 3v4M3 5h4');

    config()->set('error-pages.actions.404', [[
        'label' => 'Try our search',
        'url' => '/search',
        'icon' => 'sparkles',
    ]]);

    $this->get('/missing')->assertSee('M5 3v4M3 5h4', false);
});
