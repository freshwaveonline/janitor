@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    use FreshwaveOnline\Janitor\Support\Icons;
@endphp

<main class="jn-card" role="main">
    <header class="jn-header">
        <div class="jn-emblem" aria-hidden="true">
            {!! Icons::svg($error->icon) !!}
        </div>

        <div class="jn-heading">
            <p class="jn-status">
                <span class="jn-sr-only">{{ __('janitor::ui.meta.status') }}</span>
                <b>{{ $error->statusCode }}</b>
            </p>

            <h1 class="jn-title">{{ $error->title }}</h1>
            <p class="jn-lead">{{ $error->message }}</p>
        </div>
    </header>

    @if ($error->reason || $error->explanation)
        <section class="jn-block" aria-labelledby="jn-reason-label">
            <span class="jn-block__icon">{!! Icons::svg('information-circle') !!}</span>

            <div class="jn-block__body">
                <span class="jn-block__label" id="jn-reason-label">{{ __('janitor::ui.headings.reason') }}</span>
                @if ($error->reason)
                    <p>{{ $error->reason }}</p>
                @endif
                @if ($error->explanation)
                    <p>{{ $error->explanation }}</p>
                @endif
            </div>
        </section>
    @endif

    @include('janitor::partials.retry')

    @if ($error->suggestions !== [])
        <section class="jn-section" aria-labelledby="jn-suggestions-label">
            <h2 class="jn-section__title" id="jn-suggestions-label">{{ __('janitor::ui.headings.suggestions') }}</h2>

            <ul class="jn-list">
                @foreach ($error->suggestions as $suggestion)
                    <li>{!! Icons::svg('check-circle') !!}<span>{{ $suggestion }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @include('janitor::partials.actions')

    @include('janitor::partials.meta')

    @include('janitor::partials.details')
</main>

@if ($error->branding->supportEmail)
    <p class="jn-support">
        <span>{{ __('janitor::ui.headings.support') }}</span>
        <a href="{{ $error->supportMailto(config('janitor.links.support_subject')) }}">{{ $error->branding->supportEmail }}</a>
        @if ($error->messageNumber)
            <span>{{ __('janitor::ui.support.with_number', ['number' => $error->messageNumber]) }}</span>
        @endif
    </p>
@endif
