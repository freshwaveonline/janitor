@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    use FreshwaveOnline\Janitor\Support\Icons;

    $showTimestamp = config('janitor.show_timestamp') === true;
    $hasMeta = $error->messageNumber !== null || $error->requestId !== null || $showTimestamp;
@endphp

@if ($hasMeta)
    <div class="jn-meta">
        @if ($error->messageNumber)
            {{-- The one string support will ask for; make it a single click to copy. --}}
            <button type="button"
                    class="jn-chip"
                    data-jn-copy="{{ $error->messageNumber }}"
                    data-jn-copy-label="{{ __('janitor::ui.actions.copied') }}"
                    title="{{ __('janitor::ui.meta.copy_hint') }}">
                {!! Icons::svg('hashtag') !!}
                <span class="jn-chip__label">{{ __('janitor::ui.meta.message_number') }}</span>
                <span class="jn-chip__value">{{ $error->messageNumber }}</span>
            </button>
        @endif

        @if ($error->requestId)
            <button type="button"
                    class="jn-chip"
                    data-jn-copy="{{ $error->requestId }}"
                    data-jn-copy-label="{{ __('janitor::ui.actions.copied') }}"
                    title="{{ __('janitor::ui.meta.copy_hint') }}">
                {!! Icons::svg('fingerprint') !!}
                <span class="jn-chip__label">{{ __('janitor::ui.meta.request_id') }}</span>
                <span class="jn-chip__value">{{ $error->requestId }}</span>
            </button>
        @endif

        @if ($showTimestamp)
            <span class="jn-chip">
                {!! Icons::svg('clock') !!}
                <span class="jn-chip__label">{{ __('janitor::ui.meta.timestamp') }}</span>
                <time class="jn-chip__value" datetime="{{ $error->occurredAt->toIso8601String() }}">
                    {{ $error->occurredAt->translatedFormat((string) config('janitor.retry_after.datetime_format', 'j M Y, H:i')) }}
                </time>
            </span>
        @endif
    </div>
@endif
