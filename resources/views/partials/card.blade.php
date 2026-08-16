@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    use Vvdboogaard\ErrorPages\Support\Icons;
@endphp

<main class="ep-card" role="main">
    <header class="ep-header">
        <div class="ep-emblem" aria-hidden="true">
            {!! Icons::svg($error->icon) !!}
        </div>

        <div class="ep-heading">
            <p class="ep-status">
                <span class="ep-sr-only">{{ __('error-pages::ui.meta.status') }}</span>
                <b>{{ $error->statusCode }}</b>
            </p>

            <h1 class="ep-title">{{ $error->title }}</h1>
            <p class="ep-lead">{{ $error->message }}</p>
        </div>
    </header>

    @if ($error->reason || $error->explanation)
        <section class="ep-block" aria-labelledby="ep-reason-label">
            <span class="ep-block__icon">{!! Icons::svg('information-circle') !!}</span>

            <div class="ep-block__body">
                <span class="ep-block__label" id="ep-reason-label">{{ __('error-pages::ui.headings.reason') }}</span>
                @if ($error->reason)
                    <p>{{ $error->reason }}</p>
                @endif
                @if ($error->explanation)
                    <p>{{ $error->explanation }}</p>
                @endif
            </div>
        </section>
    @endif

    @include('error-pages::partials.retry')

    @if ($error->suggestions !== [])
        <section class="ep-section" aria-labelledby="ep-suggestions-label">
            <h2 class="ep-section__title" id="ep-suggestions-label">{{ __('error-pages::ui.headings.suggestions') }}</h2>

            <ul class="ep-list">
                @foreach ($error->suggestions as $suggestion)
                    <li>{!! Icons::svg('check-circle') !!}<span>{{ $suggestion }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @include('error-pages::partials.actions')

    @include('error-pages::partials.meta')

    @include('error-pages::partials.details')
</main>

@if ($error->supportEmail)
    <p class="ep-support">
        <span>{{ __('error-pages::ui.headings.support') }}</span>
        <a href="{{ $error->supportMailto(config('error-pages.links.support_subject')) }}">{{ $error->supportEmail }}</a>
        @if ($error->messageNumber)
            <span>{{ __('error-pages::ui.support.with_number', ['number' => $error->messageNumber]) }}</span>
        @endif
    </p>
@endif
