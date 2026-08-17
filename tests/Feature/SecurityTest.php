<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Contracts\ErrorContextBuilder;
use FreshwaveOnline\Janitor\Data\ErrorContext;
use FreshwaveOnline\Janitor\Integrations\Filament;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| What may reach the browser
|--------------------------------------------------------------------------
|
| An error page is rendered from the exact inputs an attacker controls: the
| URL, the query string, request headers, and — for the codes that opt into
| it — the abort() message. Every one of those is exercised here with markup
| in it, on the page, in the clipboard report, in the JSON body and in the
| response headers.
|
*/

const PAYLOAD = '<script>alert(document.domain)</script>';

beforeEach(function (): void {
    config()->set('janitor.views.prefer_application_views', false);

    Route::middleware('web')->get('/hostile', fn () => throw new HttpException(403, PAYLOAD));
    Route::middleware('web')->get('/boom', fn () => throw new RuntimeException(PAYLOAD));
    Route::middleware('api')->get('/api/hostile', fn () => throw new HttpException(403, PAYLOAD));
});

it('escapes an abort message before it reaches the page', function (): void {
    $content = $this->get('/hostile')->getContent();

    expect($content)->not->toContain('<script>alert(')
        ->and($content)->toContain('&lt;script&gt;');
});

it('escapes the URL and query string that produced the error', function (): void {
    Route::middleware('web')->get('/search', fn () => throw new HttpException(403, 'nope'));

    config()->set('janitor.details.visibility', 'always');
    config()->set('janitor.links.support_email', 'help@example.test');

    $content = $this->get('/search?q='.urlencode(PAYLOAD))->getContent();

    expect($content)->not->toContain('<script>alert(');
});

it('keeps the exception out of the page in production', function (): void {
    // `auto` visibility with debug off and a production environment is the
    // shipping default; nothing technical may appear.
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('app.debug', false);
    config()->set('janitor.details.visibility', 'auto');

    $content = $this->get('/boom')->getContent();

    expect($content)->not->toContain('RuntimeException')
        ->and($content)->not->toContain('alert(document.domain)')
        ->and($content)->not->toContain('Technical details')
        ->and($content)->not->toContain('jn-report-payload')
        ->and($content)->not->toContain(base_path());
});

it('never uses a framework exception message as the headline', function (): void {
    // Abort messages are opt-in per status code and only for HttpException —
    // the class a developer reaches for deliberately. Anything else keeps the
    // written copy, whatever the driver decided to put in its message.
    config()->set('janitor.details.visibility', 'never');
    config()->set('janitor.except_codes', []);
    config()->set('janitor.messages.use_exception_message_codes', [400, 402, 403, 409, 410, 423, 429, 500]);

    Route::middleware('web')->get('/model', fn () => throw new RuntimeException('SQLSTATE[28000] password=hunter2'));

    $content = $this->get('/model')->getContent();

    expect($content)->not->toContain('hunter2')
        ->and($content)->toContain('Something went wrong on our side');
});

it('escapes markup inside the copyable report', function (): void {
    config()->set('janitor.details.visibility', 'always');

    $content = $this->get('/boom?q='.urlencode(PAYLOAD))->getContent();

    // The report is emitted as JSON inside a script tag; JSON_HEX_TAG has to
    // keep a closing tag in the payload from ending that script block.
    expect($content)->not->toContain('</script>alert')
        ->and($content)->toContain('jn-report-payload')
        ->and($content)->not->toContain('<script>alert(');
});

it('encodes markup in the JSON body as escape sequences', function (): void {
    $response = $this->getJson('/api/hostile');

    // The decoded value is unchanged; only the wire encoding is hardened, so a
    // body that ends up rendered as HTML somewhere cannot carry a live tag.
    expect($response->json('message'))->toBe(PAYLOAD)
        ->and($response->getContent())->not->toContain('<script>')
        ->and($response->getContent())->toContain('\\u003C');
});

it('refuses a request id that is not safe to render', function (string $header): void {
    Route::middleware('web')->get('/missing', fn () => throw new HttpException(404));

    $response = $this->withHeader('X-Request-Id', $header)->get('/missing');

    $rendered = $response->headers->get('X-Request-Id');

    expect($rendered)->not->toBe($header)
        // A rejected header is replaced by a generated id, never dropped.
        ->and($rendered)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($response->getContent())->not->toContain('alert(');
})->with([
    'markup' => [PAYLOAD],
    'quote break-out' => ['" onmouseover="alert(1)'],
    'header injection' => ["abc\r\nX-Injected: 1"],
    'control characters' => ["abc\x00\x07def"],
    'unicode' => ['🙂-request-id'],
]);

it('truncates an over-long request id instead of rejecting it', function (): void {
    Route::middleware('web')->get('/missing', fn () => throw new HttpException(404));

    $long = str_repeat('a', 500);

    $rendered = $this->withHeader('X-Request-Id', $long)->get('/missing')->headers->get('X-Request-Id');

    expect($rendered)->toBe(str_repeat('a', 128));
});

it('does not let a hostile header reach the page through the trace formats', function (string $header, string $value): void {
    Route::middleware('web')->get('/missing', fn () => throw new HttpException(404));

    $content = $this->withHeader($header, $value)->get('/missing')->getContent();

    // The page always carries its own inline script; what may never appear is
    // anything the header put there.
    expect($content)->not->toContain('alert(1)')
        ->and($content)->not->toContain('onerror=');
})->with([
    'aws' => ['X-Amzn-Trace-Id', 'Root=<script>alert(1)</script>'],
    'google' => ['X-Cloud-Trace-Context', '<img src=x onerror=alert(1)>/1;o=1'],
    'traceparent' => ['traceparent', '00-"><script>alert(1)</script>-b7ad6b71-01'],
    'cloudflare' => ['CF-Ray', '<script>alert(1)</script>'],
]);

it('refuses a javascript: URL wherever a link is built', function (string $key): void {
    Route::middleware('web')->get('/missing', fn () => throw new HttpException(404));

    config()->set('janitor.'.$key, 'javascript:alert(document.domain)');
    config()->set('janitor.actions.404', ['home', 'status_page']);

    $content = $this->get('/missing')->getContent();

    expect($content)->not->toContain('javascript:alert');
})->with([
    'home link' => ['links.home'],
    'status page' => ['links.status_page'],
]);

it('refuses a javascript: logo and a javascript: custom action', function (): void {
    Route::middleware('web')->get('/missing', fn () => throw new HttpException(404));

    config()->set('janitor.brand.logo', 'javascript:alert(1)');
    config()->set('janitor.actions.404', [
        ['label' => 'Trap', 'url' => 'javascript:alert(1)'],
    ]);

    $content = $this->get('/missing')->getContent();

    expect($content)->not->toContain('javascript:alert')
        // A dropped URL means a dropped button, not a button that goes nowhere.
        ->and($content)->not->toContain('>Trap<');
});

it('carries branding through the Livewire payload without an executable URL', function (): void {
    Filament::fake(false);

    config()->set('janitor.links.home', 'javascript:alert(1)');
    Route::middleware('web')->get('/missing', fn () => throw new HttpException(404));

    $payload = $this->withHeader('X-Livewire', 'true')->get('/missing')->json('janitor');

    $urls = array_column($payload['actions'], 'url');

    expect(implode(' ', array_filter($urls)))->not->toContain('javascript:');
});

it('never puts the request payload or the environment into the report', function (): void {
    config()->set('janitor.details.visibility', 'always');

    $context = app(ErrorContextBuilder::class)->make(
        request(),
        new RuntimeException('boom'),
        500,
    );

    expect($context)->toBeInstanceOf(ErrorContext::class);

    $report = $context->copyReport(['timestamp' => true, 'method' => true, 'url' => true]);

    expect($report)->not->toContain(config('app.key'))
        ->and($report)->not->toContain('APP_KEY')
        ->and($report)->toContain('boom');
});
