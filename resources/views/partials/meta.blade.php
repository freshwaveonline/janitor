@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    use Vvdboogaard\ErrorPages\Support\Icons;

    $showTimestamp = config('error-pages.show_timestamp') === true;
    $hasMeta = $error->messageNumber !== null || $error->requestId !== null || $showTimestamp;
@endphp

@if ($hasMeta)
    <div class="ep-meta">
        @if ($error->messageNumber)
            {{-- The one string support will ask for; make it a single click to copy. --}}
            <button type="button"
                    class="ep-chip"
                    data-ep-copy="{{ $error->messageNumber }}"
                    data-ep-copy-label="{{ __('error-pages::ui.actions.copied') }}"
                    title="{{ __('error-pages::ui.meta.copy_hint') }}">
                {!! Icons::svg('hashtag') !!}
                <span class="ep-chip__label">{{ __('error-pages::ui.meta.message_number') }}</span>
                <span class="ep-chip__value">{{ $error->messageNumber }}</span>
            </button>
        @endif

        @if ($error->requestId)
            <button type="button"
                    class="ep-chip"
                    data-ep-copy="{{ $error->requestId }}"
                    data-ep-copy-label="{{ __('error-pages::ui.actions.copied') }}"
                    title="{{ __('error-pages::ui.meta.copy_hint') }}">
                {!! Icons::svg('fingerprint') !!}
                <span class="ep-chip__label">{{ __('error-pages::ui.meta.request_id') }}</span>
                <span class="ep-chip__value">{{ $error->requestId }}</span>
            </button>
        @endif

        @if ($showTimestamp)
            <span class="ep-chip">
                {!! Icons::svg('clock') !!}
                <span class="ep-chip__label">{{ __('error-pages::ui.meta.timestamp') }}</span>
                <time class="ep-chip__value" datetime="{{ $error->occurredAt->toIso8601String() }}">
                    {{ $error->occurredAt->translatedFormat((string) config('error-pages.retry_after.datetime_format', 'j M Y, H:i')) }}
                </time>
            </span>
        @endif
    </div>
@endif
