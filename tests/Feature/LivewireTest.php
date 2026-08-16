<?php

declare(strict_types=1);

use FreshwaveOnline\Janitor\Enums\DetailVisibility;
use FreshwaveOnline\Janitor\Enums\LivewireErrorMode;
use FreshwaveOnline\Janitor\Enums\ModalPosition;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

beforeEach(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::post('/livewire/update', fn () => throw new RuntimeException('Component blew up'));
        Route::post('/livewire/throttled', fn () => throw new TooManyRequestsHttpException(60));
    });
});

function livewirePost(string $uri = '/livewire/update'): TestResponse
{
    return test()->withHeaders([
        'X-Livewire' => 'true',
        'Accept' => 'application/json',
    ])->postJson($uri);
}

it('returns a pop-up payload for a failed Livewire round-trip', function (): void {
    config()->set('janitor.livewire.mode', LivewireErrorMode::Modal);

    $response = livewirePost();

    $response->assertStatus(500)
        ->assertJsonStructure([
            'message',
            'janitor' => ['status', 'title', 'message', 'message_number', 'request_id', 'actions', 'labels', 'icons'],
        ]);

    expect($response->json('janitor.status'))->toBe(500);
});

it('ships only the icons the pop-up actually needs', function (): void {
    $icons = livewirePost()->json('janitor.icons');

    expect($icons)->toBeArray()
        ->and($icons)->toHaveKey('x-mark')
        ->and($icons)->toHaveKey('bug-ant')       // the 500 status icon
        ->and($icons)->not->toHaveKey('scale');   // 402 only; not needed here
});

it('includes the retry moment in the pop-up payload', function (): void {
    $response = livewirePost('/livewire/throttled');

    expect($response->json('janitor.retry_at'))->not->toBeNull()
        ->and($response->json('janitor.retry_in'))->toBeGreaterThan(0);
});

it('translates the pop-up labels', function (): void {
    app()->setLocale('nl');

    expect(livewirePost()->json('janitor.labels.message_number'))->toBe('Meldingsnummer');
});

it('returns the full page HTML in page mode', function (): void {
    config()->set('janitor.livewire.mode', LivewireErrorMode::Page);

    $response = livewirePost();

    $response->assertStatus(500);
    expect($response->getContent())->toContain('<html');
});

it('leaves Livewire alone when the mode is disabled', function (): void {
    config()->set('janitor.livewire.mode', LivewireErrorMode::Disabled);

    // Falls through to the JSON shape, not the pop-up envelope.
    livewirePost()->assertStatus(500)->assertJsonMissingPath('janitor');
});

it('exposes the technical report to the pop-up only when details are allowed', function (): void {
    config()->set('janitor.details.visibility', DetailVisibility::Always);
    expect(livewirePost()->json('janitor.copy_report'))->toContain('Component blew up');

    config()->set('janitor.details.visibility', DetailVisibility::Never);
    expect(livewirePost()->json('janitor.copy_report'))->toBeNull();
});

it('renders the pop-up handler with the configured position', function (): void {
    config()->set('janitor.livewire.position', ModalPosition::TopCenter);

    $html = view('janitor::partials.livewire-script')->render();

    expect($html)->toContain('align-items: flex-start')
        ->and($html)->toContain('justify-content: center')
        ->and($html)->toContain('"position":"top-center"');
});

it('draws a backdrop only for the centred position', function (): void {
    config()->set('janitor.livewire.position', ModalPosition::Center);
    expect(view('janitor::partials.livewire-script')->render())->toContain('.jn-modal-root::before');

    config()->set('janitor.livewire.position', ModalPosition::BottomRight);
    expect(view('janitor::partials.livewire-script')->render())->not->toContain('.jn-modal-root::before');
});

it('maps every position to a flexbox alignment', function (ModalPosition $position, string $align, string $justify): void {
    expect($position->alignItems())->toBe($align)
        ->and($position->justifyContent())->toBe($justify);
})->with([
    [ModalPosition::TopLeft, 'flex-start', 'flex-start'],
    [ModalPosition::TopCenter, 'flex-start', 'center'],
    [ModalPosition::TopRight, 'flex-start', 'flex-end'],
    [ModalPosition::MiddleLeft, 'center', 'flex-start'],
    [ModalPosition::Center, 'center', 'center'],
    [ModalPosition::MiddleRight, 'center', 'flex-end'],
    [ModalPosition::BottomLeft, 'flex-end', 'flex-start'],
    [ModalPosition::BottomCenter, 'flex-end', 'center'],
    [ModalPosition::BottomRight, 'flex-end', 'flex-end'],
]);

it('falls back to a sane position for an unknown config value', function (): void {
    expect(ModalPosition::parse('somewhere-else'))->toBe(ModalPosition::BottomRight)
        ->and(ModalPosition::parse(null))->toBe(ModalPosition::BottomRight)
        ->and(ModalPosition::parse('top-left'))->toBe(ModalPosition::TopLeft);
});
