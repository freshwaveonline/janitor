@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    use Vvdboogaard\ErrorPages\Support\Icons;

    $retryAt = $error->retryAt;
@endphp

@if ($retryAt !== null)
    @php
        $timeFormat = (string) config('error-pages.retry_after.time_format', 'H:i');
        $dateTimeFormat = (string) config('error-pages.retry_after.datetime_format', 'j M Y, H:i');

        // "at 14:05" reads better than a full date when it is minutes away.
        $sameDay = $retryAt->isSameDay(now());

        $sentence = $sameDay
            ? __('error-pages::ui.retry.at', ['time' => $retryAt->translatedFormat($timeFormat)])
            : __('error-pages::ui.retry.at_datetime', ['datetime' => $retryAt->translatedFormat($dateTimeFormat)]);

        $countdown = config('error-pages.retry_after.countdown') === true;
    @endphp

    <section class="ep-block ep-block--retry" aria-labelledby="ep-retry-label">
        <span class="ep-block__icon">{!! Icons::svg('clock') !!}</span>

        <div class="ep-block__body">
            <span class="ep-block__label" id="ep-retry-label">{{ __('error-pages::ui.retry.heading') }}</span>

            <p>
                <span>{{ $sentence }}</span>
                @if ($countdown)
                    <span class="ep-countdown"
                          data-ep-countdown="{{ $retryAt->toIso8601String() }}"
                          data-ep-countdown-now="{{ __('error-pages::ui.retry.now') }}"
                          @if (config('error-pages.retry_after.auto_reload') === true) data-ep-countdown-reload="true" @endif
                          role="timer"
                          aria-live="polite"></span>
                @endif
            </p>
        </div>
    </section>
@endif
