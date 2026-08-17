<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/*
|--------------------------------------------------------------------------
| Every state, end to end
|--------------------------------------------------------------------------
|
| One test per status code the package writes copy for, plus the codes that
| reach it without a dedicated entry. Each asserts the three things a visitor
| depends on: the right status, copy written for that status rather than a
| generic fallback, and a page that renders at all.
|
*/

function throwing(Throwable $exception): void
{
    Route::middleware('web')->get('/state', fn () => throw $exception);
}

beforeEach(function (): void {
    // Testbench's skeleton ships resources/views/errors/503.blade.php, and the
    // package rightly steps aside for an application's own error view. These
    // tests are about the package's pages, so take that deference out of the
    // picture; PreviewTest and RendersErrorPagesTest cover it on its own.
    config()->set('janitor.views.prefer_application_views', false);
});

it('renders a page written for the status', function (int $status, string $expected): void {
    throwing(new HttpException($status));

    $this->get('/state')
        ->assertStatus($status)
        ->assertSee($expected)
        // The status is always on the page, so a screenshot is enough for support.
        ->assertSee((string) $status);
})->with([
    'bad request' => [400, 'Bad request'],
    'unauthorised' => [401, 'You need to sign in'],
    'payment required' => [402, 'Payment required'],
    'forbidden' => [403, 'You do not have access'],
    'not found' => [404, 'We could not find this page'],
    'method not allowed' => [405, 'This action is not allowed here'],
    'timeout' => [408, 'The request took too long'],
    'conflict' => [409, 'This change conflicts with another one'],
    'gone' => [410, 'This page is gone'],
    'payload too large' => [413, 'That file is too large'],
    'page expired' => [419, 'Your session expired'],
    'locked' => [423, 'This item is locked'],
    'too many requests' => [429, 'Too many requests'],
    'server error' => [500, 'Something went wrong on our side'],
    'not implemented' => [501, 'This is not available yet'],
    'bad gateway' => [502, 'We could not reach the server'],
    'unavailable' => [503, 'We are temporarily unavailable'],
    'gateway timeout' => [504, 'The server took too long'],
]);

it('falls back to the family copy for a status with no entry of its own', function (int $status, string $expected): void {
    throwing(new HttpException($status));

    $this->get('/state')->assertStatus($status)->assertSee($expected);
})->with([
    'unused 4xx' => [418, 'This request could not be completed'],
    'unused 5xx' => [507, 'Something went wrong on our side'],
]);

it('renders a 405 raised by the router itself', function (): void {
    throwing(new MethodNotAllowedHttpException(['GET']));

    $this->get('/state')
        ->assertStatus(405)
        ->assertSee('This action is not allowed here')
        // The Allow header is part of the HTTP semantics and must survive.
        ->assertHeader('Allow', 'GET');
});

it('renders a 401 for an authentication exception with no login route', function (): void {
    throwing(new AuthenticationException);

    $this->get('/state')->assertStatus(401)->assertSee('You need to sign in');
});

it('leaves a 422 to Laravel by default', function (): void {
    throwing(new HttpException(422));

    $response = $this->get('/state');

    $response->assertStatus(422);
    expect($response->getContent())->not->toContain('jn-card');
});

it('renders a 422 once it is taken off the exclusion list', function (): void {
    config()->set('janitor.except_codes', []);
    throwing(new HttpException(422));

    $this->get('/state')
        ->assertStatus(422)
        // No entry of its own, so it inherits the 4xx family copy.
        ->assertSee('This request could not be completed');
});

it('offers a retry countdown for the states that carry one', function (Throwable $exception, int $status): void {
    throwing($exception);

    $this->get('/state')
        ->assertStatus($status)
        ->assertHeader('Retry-After')
        ->assertSee('data-jn-countdown', false);
})->with([
    'rate limited' => [fn () => new TooManyRequestsHttpException(120), 429],
    'maintenance' => [fn () => new ServiceUnavailableHttpException(600), 503],
]);

it('treats a status outside the error range as a server error', function (int $status): void {
    // abort(302) and friends are a misuse of HttpException. Rendering the 5xx
    // page beats rendering a "302" page that means nothing to the visitor.
    throwing(new HttpException($status));

    $this->get('/state')
        ->assertStatus(500)
        ->assertSee('Something went wrong on our side');
})->with([
    'redirect' => [302],
    'nonsense' => [700],
]);

it('gives every rendered state a message number and a request id', function (int $status): void {
    throwing(new HttpException($status));

    $this->get('/state')
        ->assertStatus($status)
        ->assertHeader('X-Request-Id')
        ->assertHeader('X-Message-Number')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with([400, 401, 403, 404, 405, 419, 429, 500, 503]);
