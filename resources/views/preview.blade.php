@php
    /** @var array<int, \FreshwaveOnline\Janitor\Data\ErrorContext> $contexts */
    /** @var string $basePath */
    use FreshwaveOnline\Janitor\Support\Icons;

    $error = reset($contexts);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Error page preview</title>
    @include('janitor::partials.styles')
    <style>
        body { justify-content: flex-start; }
        .jn-shell { max-width: 56rem; }
        .jn-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); gap: .75rem; }

        .jn-preview-card {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            padding: 1rem;
            border: 1px solid var(--jn-border);
            border-radius: .75rem;
            background-color: var(--jn-surface);
            color: inherit;
            text-decoration: none;
            transition: border-color .15s ease, transform .1s ease;
        }

        .jn-preview-card:hover { border-color: var(--jn-primary-border); transform: translateY(-1px); }
        .jn-preview-card:focus-visible { outline: 2px solid var(--jn-primary-ring); outline-offset: 2px; }
        .jn-preview-card__top { display: flex; align-items: center; gap: .5rem; color: var(--jn-primary-text); }
        .jn-preview-card__top svg { width: 1.125rem; height: 1.125rem; }
        .jn-preview-card__code { font-weight: 700; letter-spacing: -.01em; color: var(--jn-text); }
        .jn-preview-card__title { font-size: .875rem; color: var(--jn-text-muted); }
        .jn-preview-card__number {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .6875rem;
            color: var(--jn-text-subtle);
        }
        .jn-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; }
    </style>
</head>
<body>
    <div class="jn-shell">
        <main class="jn-card">
            <header class="jn-header">
                <div class="jn-emblem" aria-hidden="true">{!! Icons::svg('document-text') !!}</div>
                <div class="jn-heading">
                    <p class="jn-status"><b>Preview</b></p>
                    <h1 class="jn-title">Error pages</h1>
                    <p class="jn-lead">Every state this package renders, without provoking a real error.</p>
                </div>
            </header>

            <div class="jn-toolbar">
                <a class="jn-btn jn-btn--secondary" href="?theme=light">Light</a>
                <a class="jn-btn jn-btn--secondary" href="?theme=dark">Dark</a>
            </div>

            <div class="jn-grid">
                @foreach ($contexts as $code => $context)
                    <a class="jn-preview-card" href="{{ url($basePath.'/'.$code) }}">
                        <span class="jn-preview-card__top">
                            {!! Icons::svg($context->icon) !!}
                            <span class="jn-preview-card__code">{{ $code }}</span>
                        </span>
                        <span class="jn-preview-card__title">{{ $context->title }}</span>
                        @if ($context->messageNumber)
                            <span class="jn-preview-card__number">{{ $context->messageNumber }}</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <section class="jn-section">
                <h2 class="jn-section__title">Query parameters</h2>
                <ul class="jn-list">
                    <li>{!! Icons::svg('check-circle') !!}<span><code>?theme=dark</code> — force a colour scheme</span></li>
                    <li>{!! Icons::svg('check-circle') !!}<span><code>?details=1</code> — force the technical block on or off</span></li>
                    <li>{!! Icons::svg('check-circle') !!}<span><code>?retry=120</code> — set the retry countdown in seconds (429, 503)</span></li>
                    <li>{!! Icons::svg('check-circle') !!}<span><code>?modal=1</code> — preview the Livewire pop-up</span></li>
                    <li>{!! Icons::svg('check-circle') !!}<span><code>?position=top-right</code> — move the pop-up</span></li>
                </ul>
            </section>
        </main>
    </div>
</body>
</html>
