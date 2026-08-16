@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    use FreshwaveOnline\Janitor\Support\Icons;

    $details = $error->details;
@endphp

@if ($details !== null)
    @php
        $copyable = config('janitor.details.copyable') === true;
        $collapsed = config('janitor.details.collapsed') === true;

        /** @var array<string, bool> $includes */
        $includes = (array) config('janitor.details.copy_includes', []);
        $report = $error->copyReport($includes);
    @endphp

    <details class="jn-details" @if (! $collapsed) open @endif>
        <summary>
            {!! Icons::svg('bug-ant') !!}
            <span>{{ __('janitor::ui.details.heading') }}</span>
            {!! Icons::svg('chevron-down', ['class' => 'jn-details__chevron jn-details__spacer']) !!}
        </summary>

        <div class="jn-details__body">
            <p class="jn-block__label">{{ __('janitor::ui.details.intro') }}</p>

            <div class="jn-exception">
                <div class="jn-exception__class">{{ $details->class }}</div>
                <div class="jn-exception__message">{{ $details->message }}</div>
                <div class="jn-exception__location">{{ $details->location() }}</div>
                @if ($details->previous)
                    <div class="jn-exception__location">
                        {{ __('janitor::ui.details.caused_by') }}: {{ $details->previous }}
                    </div>
                @endif
            </div>

            @if ($details->frames !== [])
                <div class="jn-trace" role="group" aria-label="{{ __('janitor::ui.details.stack_trace') }}">
                    @foreach ($details->frames as $index => $frame)
                        <div @class(['jn-trace--vendor' => $frame['vendor']])><span
                                class="jn-trace__index">#{{ $index }}</span> {{ $frame['file'] }}:{{ $frame['line'] }} — {{ $frame['call'] }}</div>
                    @endforeach
                </div>
            @endif

            @if ($copyable)
                {{--
                    The report is emitted as JSON rather than dropped into an
                    attribute so newlines survive and nothing can break out of
                    the surrounding markup.
                --}}
                <script type="application/json" id="jn-report-payload">@json($report, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

                <div class="jn-actions">
                    <button type="button"
                            class="jn-btn jn-btn--secondary"
                            data-jn-copy-from="#jn-report-payload"
                            data-jn-copy-label="{{ __('janitor::ui.actions.copied') }}">
                        {!! Icons::svg('clipboard') !!}
                        <span>{{ __('janitor::ui.details.copy') }}</span>
                    </button>
                </div>
            @endif
        </div>
    </details>
@endif
