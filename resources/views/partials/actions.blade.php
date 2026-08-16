@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    use Vvdboogaard\ErrorPages\Support\Icons;

    $actions = $error->actions();

    // Marked, but never disabled server-side: without JavaScript there is no
    // countdown to re-enable the button, and a permanently dead retry button is
    // worse than one that can be pressed a few seconds early. The countdown
    // script disables it on load and releases it when the moment arrives.
    $waiting = $error->hasRetry() && ($error->retryInSeconds() ?? 0) > 0;
@endphp

@if ($actions !== [])
    <nav class="ep-actions" aria-label="{{ __('error-pages::ui.headings.suggestions') }}">
        @foreach ($actions as $action)
            @php
                $disabled = $action->waitsForRetry && $waiting;
                $classes = 'ep-btn ep-btn--'.$action->style;
            @endphp

            @if ($action->tag() === 'a')
                <a class="{{ $classes }}"
                   href="{{ $action->url }}"
                   @if ($action->key === 'home') data-ep-home @endif
                   @if ($action->external) target="_blank" rel="noopener noreferrer" @endif
                   @if ($disabled) aria-disabled="true" data-ep-wait-for-retry @endif>
                    @if ($action->icon){!! Icons::svg($action->icon) !!}@endif
                    <span>{{ $action->label }}</span>
                </a>
            @else
                <button type="button"
                        class="{{ $classes }}"
                        data-ep-action="{{ $action->behaviour }}"
                        @if ($disabled) data-ep-wait-for-retry @endif>
                    @if ($action->icon){!! Icons::svg($action->icon) !!}@endif
                    <span>{{ $action->label }}</span>
                </button>
            @endif
        @endforeach
    </nav>
@endif
