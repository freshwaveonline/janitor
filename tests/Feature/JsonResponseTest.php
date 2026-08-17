<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/*
|--------------------------------------------------------------------------
| The machine-readable shape
|--------------------------------------------------------------------------
|
| An API client gets the same information as the page: what happened, the
| code to quote, and — when there is one — when to come back.
|
*/

beforeEach(function (): void {
    config()->set('janitor.views.prefer_application_views', false);

    Route::middleware('api')->get('/api/thing', fn () => throw new HttpException(403, 'You may not edit published posts.'));
    Route::middleware('api')->get('/api/limited', fn () => throw new TooManyRequestsHttpException(90));
    Route::middleware('api')->get('/api/broken', fn () => throw new RuntimeException('kaboom'));
});

it('answers a JSON request with JSON', function (): void {
    $response = $this->getJson('/api/thing');

    $response->assertStatus(403)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['status', 'title', 'message', 'message_number', 'request_id']);

    expect($response->json('status'))->toBe(403)
        ->and($response->json('title'))->toBe('You do not have access')
        // 403 is on `use_exception_message_codes`, so a deliberate abort()
        // message reaches the client.
        ->and($response->json('message'))->toBe('You may not edit published posts.')
        ->and($response->json('message_number'))->toStartWith('ERR-')
        ->and($response->json('request_id'))->toBeString();
});

it('repeats the message number and request id in the headers too', function (): void {
    $response = $this->getJson('/api/thing');

    expect($response->headers->get('X-Message-Number'))->toBe($response->json('message_number'))
        ->and($response->headers->get('X-Request-Id'))->toBe($response->json('request_id'));
});

it('carries the retry moment in both the body and the header', function (): void {
    $response = $this->getJson('/api/limited');

    $response->assertStatus(429)->assertHeader('Retry-After');

    expect($response->json('retry_after'))->toBeGreaterThan(0)
        ->and($response->json('retry_after'))->toBeLessThanOrEqual(90)
        ->and($response->json('retry_at'))->toBeString();
});

it('never puts the exception in the body when details are not allowed', function (): void {
    config()->set('janitor.details.visibility', 'never');

    $response = $this->getJson('/api/broken');

    $response->assertStatus(500)->assertJsonMissingPath('exception');
    expect($response->getContent())->not->toContain('kaboom');
});

it('includes the exception once the environment allows it', function (): void {
    config()->set('janitor.details.visibility', 'always');

    $response = $this->getJson('/api/broken');

    expect($response->json('exception.class'))->toBe(RuntimeException::class)
        ->and($response->json('exception.message'))->toBe('kaboom')
        ->and($response->json('exception.frames'))->toBeArray();
});

it('drops each optional field when its toggle is off', function (string $option, string $key): void {
    config()->set('janitor.details.visibility', 'always');
    config()->set('janitor.json.'.$option, false);

    $this->getJson('/api/limited')->assertJsonMissingPath($key);
})->with([
    'message number' => ['include_message_number', 'message_number'],
    'request id' => ['include_request_id', 'request_id'],
    'retry moment' => ['include_retry_after', 'retry_after'],
    'exception' => ['include_details', 'exception'],
]);

it('renders the HTML page instead when JSON is turned off', function (): void {
    config()->set('janitor.json.enabled', false);

    $response = $this->getJson('/api/thing');

    $response->assertStatus(403);
    expect($response->getContent())->toContain('jn-card');
});
