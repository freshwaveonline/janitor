@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    use FreshwaveOnline\Janitor\Support\Icons;

    $retryAt = $error->retryAt;
@endphp

@if ($retryAt !== null)
    @php
        $timeFormat = (string) config('janitor.retry_after.time_format', 'H:i');
        $dateTimeFormat = (string) config('janitor.retry_after.datetime_format', 'j M Y, H:i');

        // "at 14:05" reads better than a full date when it is minutes away.
        $sameDay = $retryAt->isSameDay(now());

        $sentence = $sameDay
            ? __('janitor::ui.retry.at', ['time' => $retryAt->translatedFormat($timeFormat)])
            : __('janitor::ui.retry.at_datetime', ['datetime' => $retryAt->translatedFormat($dateTimeFormat)]);

        $countdown = config('janitor.retry_after.countdown') === true;
    @endphp

    <section class="jn-block jn-block--retry" aria-labelledby="jn-retry-label">
        <span class="jn-block__icon">{!! Icons::svg('clock') !!}</span>

        <div class="jn-block__body">
            <span class="jn-block__label" id="jn-retry-label">{{ __('janitor::ui.retry.heading') }}</span>

            <p>
                <span>{{ $sentence }}</span>
                @if ($countdown)
                    <span class="jn-countdown"
                          data-jn-countdown="{{ $retryAt->toIso8601String() }}"
                          data-jn-countdown-now="{{ __('janitor::ui.retry.now') }}"
                          @if (config('janitor.retry_after.auto_reload') === true) data-jn-countdown-reload="true" @endif
                          role="timer"
                          aria-live="polite"></span>
                @endif
            </p>
        </div>
    </section>
@endif
