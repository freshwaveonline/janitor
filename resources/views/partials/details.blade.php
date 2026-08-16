@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    use Vvdboogaard\ErrorPages\Support\Icons;

    $details = $error->details;
@endphp

@if ($details !== null)
    @php
        $copyable = config('error-pages.details.copyable') === true;
        $collapsed = config('error-pages.details.collapsed') === true;

        /** @var array<string, bool> $includes */
        $includes = (array) config('error-pages.details.copy_includes', []);
        $report = $error->copyReport($includes);
    @endphp

    <details class="ep-details" @if (! $collapsed) open @endif>
        <summary>
            {!! Icons::svg('bug-ant') !!}
            <span>{{ __('error-pages::ui.details.heading') }}</span>
            {!! Icons::svg('chevron-down', ['class' => 'ep-details__chevron ep-details__spacer']) !!}
        </summary>

        <div class="ep-details__body">
            <p class="ep-block__label">{{ __('error-pages::ui.details.intro') }}</p>

            <div class="ep-exception">
                <div class="ep-exception__class">{{ $details->class }}</div>
                <div class="ep-exception__message">{{ $details->message }}</div>
                <div class="ep-exception__location">{{ $details->location() }}</div>
                @if ($details->previous)
                    <div class="ep-exception__location">
                        {{ __('error-pages::ui.details.caused_by') }}: {{ $details->previous }}
                    </div>
                @endif
            </div>

            @if ($details->frames !== [])
                <div class="ep-trace" role="group" aria-label="{{ __('error-pages::ui.details.stack_trace') }}">
                    @foreach ($details->frames as $index => $frame)
                        <div @class(['ep-trace--vendor' => $frame['vendor']])><span
                                class="ep-trace__index">#{{ $index }}</span> {{ $frame['file'] }}:{{ $frame['line'] }} — {{ $frame['call'] }}</div>
                    @endforeach
                </div>
            @endif

            @if ($copyable)
                {{--
                    The report is emitted as JSON rather than dropped into an
                    attribute so newlines survive and nothing can break out of
                    the surrounding markup.
                --}}
                <script type="application/json" id="ep-report-payload">@json($report, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

                <div class="ep-actions">
                    <button type="button"
                            class="ep-btn ep-btn--secondary"
                            data-ep-copy-from="#ep-report-payload"
                            data-ep-copy-label="{{ __('error-pages::ui.actions.copied') }}">
                        {!! Icons::svg('clipboard') !!}
                        <span>{{ __('error-pages::ui.details.copy') }}</span>
                    </button>
                </div>
            @endif
        </div>
    </details>
@endif
