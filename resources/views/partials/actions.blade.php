@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    use FreshwaveOnline\Janitor\Support\Icons;

    $actions = $error->actions();

    // Marked, but never disabled server-side: without JavaScript there is no
    // countdown to re-enable the button, and a permanently dead retry button is
    // worse than one that can be pressed a few seconds early. The countdown
    // script disables it on load and releases it when the moment arrives.
    $waiting = $error->hasRetry() && ($error->retryInSeconds() ?? 0) > 0;
@endphp

@if ($actions !== [])
    <nav class="jn-actions" aria-label="{{ __('janitor::ui.headings.suggestions') }}">
        @foreach ($actions as $action)
            @php
                $disabled = $action->waitsForRetry && $waiting;
                $classes = 'jn-btn jn-btn--'.$action->style;
            @endphp

            @if ($action->tag() === 'a')
                <a class="{{ $classes }}"
                   href="{{ $action->url }}"
                   @if ($action->key === 'home') data-jn-home @endif
                   @if ($action->external) target="_blank" rel="noopener noreferrer" @endif
                   @if ($disabled) aria-disabled="true" data-jn-wait-for-retry @endif>
                    @if ($action->icon){!! Icons::svg($action->icon) !!}@endif
                    <span>{{ $action->label }}</span>
                </a>
            @else
                <button type="button"
                        class="{{ $classes }}"
                        data-jn-action="{{ $action->behaviour }}"
                        @if ($disabled) data-jn-wait-for-retry @endif>
                    @if ($action->icon){!! Icons::svg($action->icon) !!}@endif
                    <span>{{ $action->label }}</span>
                </button>
            @endif
        @endforeach
    </nav>
@endif
