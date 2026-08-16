@php
    /** @var array<int, \Vvdboogaard\ErrorPages\Data\ErrorContext> $contexts */
    /** @var string $basePath */
    use Vvdboogaard\ErrorPages\Support\Icons;

    $error = reset($contexts);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Error page preview</title>
    @include('error-pages::partials.styles')
    <style>
        body { justify-content: flex-start; }
        .ep-shell { max-width: 56rem; }
        .ep-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); gap: .75rem; }

        .ep-preview-card {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            padding: 1rem;
            border: 1px solid var(--ep-border);
            border-radius: .75rem;
            background-color: var(--ep-surface);
            color: inherit;
            text-decoration: none;
            transition: border-color .15s ease, transform .1s ease;
        }

        .ep-preview-card:hover { border-color: var(--ep-primary-border); transform: translateY(-1px); }
        .ep-preview-card:focus-visible { outline: 2px solid var(--ep-primary-ring); outline-offset: 2px; }
        .ep-preview-card__top { display: flex; align-items: center; gap: .5rem; color: var(--ep-primary-text); }
        .ep-preview-card__top svg { width: 1.125rem; height: 1.125rem; }
        .ep-preview-card__code { font-weight: 700; letter-spacing: -.01em; color: var(--ep-text); }
        .ep-preview-card__title { font-size: .875rem; color: var(--ep-text-muted); }
        .ep-preview-card__number {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .6875rem;
            color: var(--ep-text-subtle);
        }
        .ep-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; }
    </style>
</head>
<body>
    <div class="ep-shell">
        <main class="ep-card">
            <header class="ep-header">
                <div class="ep-emblem" aria-hidden="true">{!! Icons::svg('document-text') !!}</div>
                <div class="ep-heading">
                    <p class="ep-status"><b>Preview</b></p>
                    <h1 class="ep-title">Error pages</h1>
                    <p class="ep-lead">Every state this package renders, without provoking a real error.</p>
                </div>
            </header>

            <div class="ep-toolbar">
                <a class="ep-btn ep-btn--secondary" href="?theme=light">Light</a>
                <a class="ep-btn ep-btn--secondary" href="?theme=dark">Dark</a>
            </div>

            <div class="ep-grid">
                @foreach ($contexts as $code => $context)
                    <a class="ep-preview-card" href="{{ url($basePath.'/'.$code) }}">
                        <span class="ep-preview-card__top">
                            {!! Icons::svg($context->icon) !!}
                            <span class="ep-preview-card__code">{{ $code }}</span>
                        </span>
                        <span class="ep-preview-card__title">{{ $context->title }}</span>
                        @if ($context->messageNumber)
                            <span class="ep-preview-card__number">{{ $context->messageNumber }}</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <section class="ep-section">
                <h2 class="ep-section__title">Query parameters</h2>
                <ul class="ep-list">
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
