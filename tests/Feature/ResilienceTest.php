<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Contracts\ActionResolver;
use FreshwaveOnline\Janitor\Contracts\BrandingResolver;
use FreshwaveOnline\Janitor\Contracts\ErrorContextBuilder;
use FreshwaveOnline\Janitor\Contracts\ErrorRenderer;
use FreshwaveOnline\Janitor\Contracts\MessageNumberGenerator;
use FreshwaveOnline\Janitor\Contracts\RequestIdResolver;
use FreshwaveOnline\Janitor\Contracts\RetryAfterResolver;
use FreshwaveOnline\Janitor\Data\ErrorContext;
use FreshwaveOnline\Janitor\Support\ActionFactory;
use FreshwaveOnline\Janitor\Support\ConfigBranding;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Rendering while the application is already broken
|--------------------------------------------------------------------------
|
| This package runs after something has failed, so the things it reads from
| may be part of what failed. Losing a line of copy or a button is fine.
| Throwing a second exception is not: the visitor would get a blank 500
| instead of the page explaining the first one.
|
*/

beforeEach(function (): void {
    config()->set('janitor.views.prefer_application_views', false);

    Route::middleware('web')->get('/missing', fn () => throw new NotFoundHttpException);
    Route::middleware('web')->get('/boom', fn () => throw new RuntimeException('kaboom'));
});

it('still renders when the translator throws', function (): void {
    app()->bind('translator', fn () => new class
    {
        public function has(): never
        {
            throw new RuntimeException('no translations');
        }

        public function get(): never
        {
            throw new RuntimeException('no translations');
        }

        public function getLocale(): string
        {
            return 'en';
        }
    });

    $response = $this->get('/missing');

    $response->assertStatus(404);
    // Without copy, the status code is the headline — still a real page.
    expect($response->getContent())->toContain('404');
});

it('still renders the full page when the branding resolver throws', function (): void {
    app()->bind(BrandingResolver::class, fn () => new class implements BrandingResolver
    {
        public function resolve(Request $request, int $statusCode): never
        {
            throw new RuntimeException('tenant lookup failed');
        }
    });

    $this->get('/missing')->assertStatus(404)->assertSee('We could not find this page');
});

it('still renders the full page when the action resolver throws', function (): void {
    app()->bind(ActionResolver::class, fn () => new class implements ActionResolver
    {
        public function for(ErrorContext $context, Request $request): never
        {
            throw new RuntimeException('no routes');
        }
    });

    $this->get('/missing')->assertStatus(404)->assertSee('We could not find this page');
});

it('still renders the full page when the message number generator throws', function (): void {
    app()->bind(MessageNumberGenerator::class, fn () => new class implements MessageNumberGenerator
    {
        public function for(?Throwable $exception, int $statusCode): never
        {
            throw new RuntimeException('hashing failed');
        }

        public function fingerprint(?Throwable $exception, int $statusCode): never
        {
            throw new RuntimeException('hashing failed');
        }
    });

    $this->get('/missing')->assertStatus(404)->assertSee('We could not find this page');
});

it('still renders the full page when the request id resolver throws', function (): void {
    app()->bind(RequestIdResolver::class, fn () => new class implements RequestIdResolver
    {
        public function resolve(Request $request): never
        {
            throw new RuntimeException('no correlation id');
        }

        public function responseHeader(): ?string
        {
            return 'X-Request-Id';
        }
    });

    $this->get('/missing')->assertStatus(404)->assertSee('We could not find this page');
});

it('still renders the full page when the retry resolver throws', function (): void {
    app()->bind(RetryAfterResolver::class, fn () => new class implements RetryAfterResolver
    {
        public function resolve(?Throwable $exception, ?Request $request = null): never
        {
            throw new RuntimeException('no clock');
        }
    });

    $this->get('/missing')->assertStatus(404)->assertSee('We could not find this page');
});

it('falls back to a plain page when the view cannot be rendered', function (): void {
    config()->set('janitor.views.page', 'janitor::does-not-exist');

    $response = $this->get('/missing');
    $content = $response->getContent();

    $response->assertStatus(404);

    expect($content)->toContain('404')
        ->toContain('Page not found')
        // The real page is gone, so its markup must be gone too.
        ->not->toContain('jn-card');
});

it('answers with plain JSON for an API client when the context cannot be built', function (): void {
    // A broken view alone never reaches an API client — the JSON shape does not
    // render one. It takes a failure earlier than that.
    app()->bind(ErrorContextBuilder::class, fn () => new class implements ErrorContextBuilder
    {
        public function make(Request $request, ?Throwable $exception, ?int $statusCode = null): never
        {
            throw new RuntimeException('cannot build a context');
        }

        public function statusCode(?Throwable $exception): int
        {
            return 404;
        }
    });

    Route::middleware('api')->get('/api/missing', fn () => throw new NotFoundHttpException);

    $this->getJson('/api/missing')
        ->assertStatus(404)
        ->assertExactJson(['message' => 'Page not found', 'status' => 404]);
});

it('never leaks the exception through the fallback page', function (): void {
    config()->set('janitor.views.page', 'janitor::does-not-exist');
    config()->set('janitor.details.visibility', 'always');

    $content = $this->get('/boom')->getContent();

    expect($content)->not->toContain('kaboom')
        ->and($content)->not->toContain('RuntimeException')
        ->and($content)->toContain('Something went wrong');
});

it('marks the fallback page as noindex', function (): void {
    config()->set('janitor.views.page', 'janitor::does-not-exist');

    expect($this->get('/missing')->getContent())->toContain('noindex, nofollow');
});

it('hands the exception back to Laravel when its own config cannot be read', function (): void {
    // Snapshot first: resolving `config` from inside its own binding would
    // recurse forever.
    $items = config()->all();

    app()->instance('config', new class($items) extends Repository
    {
        public function get($key, $default = null): mixed
        {
            if (is_string($key) && str_starts_with($key, 'janitor.')) {
                throw new RuntimeException('config cache is corrupt');
            }

            return parent::get($key, $default);
        }
    });

    // Unable to tell whether it is even enabled, the package steps aside and
    // Laravel's own handler renders. No second exception escapes.
    $response = $this->get('/missing');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->not->toContain('jn-card');
});

it('renders a page without a URL generator or a router', function (): void {
    // A `home` action with no way to build its URL drops the button rather
    // than the page.
    app()->bind(ConfigBranding::class, fn ($app) => new class($app->make('config')) extends ConfigBranding
    {
        protected function homeUrl(Request $request): ?string
        {
            throw new RuntimeException('no url generator');
        }
    });

    $this->get('/missing')->assertStatus(404);
});

it('drops a button whose route name cannot be resolved', function (): void {
    config()->set('janitor.actions.404', [
        ['label' => 'Dashboard', 'url' => 'route.that.does.not.exist'],
        'back',
    ]);

    $this->get('/missing')->assertStatus(404)->assertSee('Go back');
});

it('keeps the injected assets middleware from breaking a working page', function (): void {
    config()->set('janitor.views.page', 'janitor::error');

    Route::middleware('web')->get('/fine', fn () => response('<html><body>Fine</body></html>'));

    $this->get('/fine')->assertOk()->assertSee('Fine');
});

it('does not double-fault when the renderer itself is replaced by a broken one', function (): void {
    app()->bind(ErrorRenderer::class, fn () => new class implements ErrorRenderer
    {
        public function render(Request $request, Throwable $exception): never
        {
            throw new RuntimeException('renderer is broken');
        }

        public function shouldHandle(Request $request, Throwable $exception): bool
        {
            return true;
        }

        public function statusFor(Throwable $exception): int
        {
            return 404;
        }

        public function viewName(int $status): string
        {
            return 'janitor::error';
        }

        public function renderContext(Request $request, ErrorContext $context): never
        {
            throw new RuntimeException('renderer is broken');
        }

        public function isLivewireRequest(Request $request): bool
        {
            return false;
        }

        public function livewirePayload(ErrorContext $context): array
        {
            return [];
        }
    });

    $response = $this->get('/missing');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->not->toContain('renderer is broken');
});

it('builds a context even for an exception with no useful origin', function (): void {
    $context = app(ErrorContextBuilder::class)->make(
        Request::create('/somewhere'),
        new HttpException(404),
        404,
    );

    expect($context->statusCode)->toBe(404)
        ->and($context->messageNumber)->toBeString()
        ->and($context->title)->not->toBe('');
});

it('resolves the default action factory even with an empty actions map', function (): void {
    config()->set('janitor.actions', []);

    $actions = app(ActionFactory::class)->for(
        app(ErrorContextBuilder::class)->make(Request::create('/x'), new HttpException(404), 404),
        Request::create('/x'),
    );

    // 'back' and 'home' are the built-in default when nothing is configured.
    expect(array_column(array_map(fn ($action) => $action->toArray(), $actions), 'key'))
        ->toContain('back');
});
