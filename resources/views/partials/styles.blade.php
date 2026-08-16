{{--
    Inline stylesheet.

    Deliberately not a published asset: an error page has to render when the
    asset pipeline, the CDN or the network is exactly what is broken. Every
    colour comes from a custom property so the whole surface follows the
    configured primary colour and the visitor's colour scheme.
--}}
@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    $theme = $error->theme;
    $palette = $error->palette;
@endphp
<style>
    :root {
        color-scheme: {{ $theme->colorScheme() }};
@if ($theme !== \Vvdboogaard\ErrorPages\Enums\Theme::Dark)
{!! $palette->declarations('light', '        ') !!}
@else
{!! $palette->declarations('dark', '        ') !!}
@endif
    }

@if ($theme === \Vvdboogaard\ErrorPages\Enums\Theme::Auto)
    @media (prefers-color-scheme: dark) {
        :root:not([data-ep-theme="light"]) {
{!! $palette->declarations('dark', '            ') !!}
        }
    }

    [data-ep-theme="dark"] {
{!! $palette->declarations('dark', '        ') !!}
    }

    [data-ep-theme="light"] {
{!! $palette->declarations('light', '        ') !!}
    }

    @media (prefers-color-scheme: dark) {
        :root:not([data-ep-theme="light"]) .ep-brand--light { display: none; }
        :root:not([data-ep-theme="light"]) .ep-brand--dark { display: block; }
    }

    [data-ep-theme="dark"] .ep-brand--light { display: none; }
    [data-ep-theme="dark"] .ep-brand--dark { display: block; }
    [data-ep-theme="light"] .ep-brand--light { display: block; }
    [data-ep-theme="light"] .ep-brand--dark { display: none; }
@elseif ($theme === \Vvdboogaard\ErrorPages\Enums\Theme::Dark)
    .ep-brand--light { display: none; }
    .ep-brand--dark { display: block; }
@endif

    *, *::before, *::after { box-sizing: border-box; }

    html { -webkit-text-size-adjust: 100%; }

    body {
        margin: 0;
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: clamp(1rem, 4vw, 3rem) clamp(1rem, 4vw, 2rem);
        gap: 1.5rem;
        background-color: var(--ep-bg);
        background-image:
            radial-gradient(70rem 40rem at 50% -20%, var(--ep-primary-soft), transparent 70%);
        color: var(--ep-text);
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
        font-size: 16px;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
    }

    .ep-shell {
        width: 100%;
        max-width: 40rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* ---------------------------------------------------------------- brand */

    .ep-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .625rem;
        min-height: 2rem;
        color: var(--ep-text-muted);
        font-size: .9375rem;
        font-weight: 600;
        letter-spacing: -.01em;
        text-decoration: none;
    }

    .ep-brand img { display: block; width: auto; max-width: 12rem; }
    .ep-brand--dark { display: none; }

    /* ----------------------------------------------------------------- card */

    .ep-card {
        background-color: var(--ep-surface);
        border: 1px solid var(--ep-border);
        border-radius: 1rem;
        box-shadow: var(--ep-shadow);
        padding: clamp(1.5rem, 5vw, 2.5rem);
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .ep-header { display: flex; gap: 1rem; align-items: flex-start; }

    .ep-emblem {
        flex: 0 0 auto;
        width: 3rem;
        height: 3rem;
        display: grid;
        place-items: center;
        border-radius: .75rem;
        background-color: var(--ep-primary-soft);
        border: 1px solid var(--ep-primary-border);
        color: var(--ep-primary-text);
    }

    .ep-emblem svg { width: 1.5rem; height: 1.5rem; }

    .ep-heading { display: flex; flex-direction: column; gap: .375rem; min-width: 0; }

    .ep-status {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--ep-text-subtle);
    }

    .ep-status b { color: var(--ep-text-muted); font-weight: 700; letter-spacing: .04em; }

    .ep-title {
        margin: 0;
        font-size: clamp(1.375rem, 1.1rem + 1.2vw, 1.75rem);
        font-weight: 650;
        line-height: 1.25;
        letter-spacing: -.02em;
        color: var(--ep-text);
        text-wrap: balance;
    }

    .ep-lead { margin: 0; color: var(--ep-text-muted); font-size: 1rem; text-wrap: pretty; }

    /* --------------------------------------------------------------- blocks */

    .ep-block {
        border: 1px solid var(--ep-border);
        border-radius: .75rem;
        background-color: var(--ep-surface-muted);
        padding: 1rem 1.125rem;
        display: flex;
        gap: .875rem;
    }

    .ep-block__icon { flex: 0 0 auto; color: var(--ep-text-subtle); margin-top: .1875rem; }
    .ep-block__icon svg { width: 1.125rem; height: 1.125rem; }
    .ep-block__body { display: flex; flex-direction: column; gap: .375rem; min-width: 0; }

    .ep-block__label {
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--ep-text-subtle);
    }

    .ep-block p { margin: 0; color: var(--ep-text-muted); font-size: .9375rem; }
    .ep-block p + p { color: var(--ep-text-subtle); }

    .ep-block--retry {
        background-color: var(--ep-primary-soft);
        border-color: var(--ep-primary-border);
    }

    .ep-block--retry .ep-block__icon { color: var(--ep-primary-text); }
    .ep-block--retry .ep-block__label { color: var(--ep-primary-text); }
    .ep-block--retry p { color: var(--ep-text); }

    .ep-countdown {
        font-variant-numeric: tabular-nums;
        font-weight: 650;
        color: var(--ep-primary-text);
    }

    /* ---------------------------------------------------------- suggestions */

    .ep-section { display: flex; flex-direction: column; gap: .75rem; }

    .ep-section__title {
        margin: 0;
        font-size: .8125rem;
        font-weight: 650;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--ep-text-subtle);
    }

    .ep-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .625rem; }

    .ep-list li {
        display: flex;
        gap: .625rem;
        align-items: flex-start;
        color: var(--ep-text-muted);
        font-size: .9375rem;
    }

    .ep-list svg { flex: 0 0 auto; width: 1.125rem; height: 1.125rem; margin-top: .1875rem; color: var(--ep-primary-text); }

    /* -------------------------------------------------------------- actions */

    .ep-actions { display: flex; flex-wrap: wrap; gap: .625rem; }

    .ep-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        padding: .625rem 1rem;
        border-radius: .625rem;
        border: 1px solid transparent;
        font: inherit;
        font-size: .9375rem;
        font-weight: 550;
        line-height: 1.25;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .1s ease;
    }

    .ep-btn svg { width: 1.125rem; height: 1.125rem; flex: 0 0 auto; }
    .ep-btn:active { transform: translateY(1px); }

    .ep-btn:focus-visible {
        outline: 2px solid var(--ep-primary-ring);
        outline-offset: 2px;
    }

    .ep-btn--primary {
        background-color: var(--ep-primary);
        border-color: var(--ep-primary);
        color: var(--ep-primary-contrast);
        box-shadow: var(--ep-shadow-sm);
    }

    .ep-btn--primary:hover { background-color: var(--ep-primary-hover); border-color: var(--ep-primary-hover); }
    .ep-btn--primary:active { background-color: var(--ep-primary-active); }

    .ep-btn--secondary {
        background-color: var(--ep-surface);
        border-color: var(--ep-border-strong);
        color: var(--ep-text);
    }

    .ep-btn--secondary:hover { background-color: var(--ep-surface-muted); border-color: var(--ep-text-subtle); }

    .ep-btn--ghost { background-color: transparent; color: var(--ep-text-muted); }
    .ep-btn--ghost:hover { background-color: var(--ep-surface-sunken); color: var(--ep-text); }

    .ep-btn[disabled], .ep-btn[aria-disabled="true"] {
        opacity: .55;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* ----------------------------------------------------------------- meta */

    .ep-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--ep-border);
    }

    .ep-chip {
        display: inline-flex;
        align-items: center;
        gap: .4375rem;
        padding: .375rem .625rem;
        border-radius: .5rem;
        border: 1px solid var(--ep-border);
        background-color: var(--ep-surface-muted);
        color: var(--ep-text-muted);
        font-size: .75rem;
        line-height: 1.4;
        font: inherit;
        font-size: .75rem;
        cursor: default;
    }

    .ep-chip svg { width: .875rem; height: .875rem; color: var(--ep-text-subtle); flex: 0 0 auto; }
    .ep-chip__label { color: var(--ep-text-subtle); }

    .ep-chip__value {
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        font-weight: 600;
        color: var(--ep-text);
        letter-spacing: .01em;
        overflow-wrap: anywhere;
    }

    button.ep-chip { cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
    button.ep-chip:hover { border-color: var(--ep-primary-border); background-color: var(--ep-primary-soft); }
    button.ep-chip:focus-visible { outline: 2px solid var(--ep-primary-ring); outline-offset: 2px; }
    button.ep-chip[data-copied="true"] { border-color: var(--ep-primary-border); background-color: var(--ep-primary-soft); }
    button.ep-chip[data-copied="true"] .ep-chip__label { color: var(--ep-primary-text); }

    /* -------------------------------------------------------------- details */

    .ep-details {
        border: 1px solid var(--ep-border);
        border-radius: .75rem;
        background-color: var(--ep-surface-sunken);
        overflow: hidden;
    }

    .ep-details > summary {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .75rem 1rem;
        cursor: pointer;
        list-style: none;
        color: var(--ep-text-muted);
        font-size: .8125rem;
        font-weight: 600;
        user-select: none;
    }

    .ep-details > summary::-webkit-details-marker { display: none; }
    .ep-details > summary:focus-visible { outline: 2px solid var(--ep-primary-ring); outline-offset: -2px; }
    .ep-details > summary svg { width: 1rem; height: 1rem; transition: transform .15s ease; }
    .ep-details[open] > summary .ep-details__chevron { transform: rotate(180deg); }
    .ep-details__spacer { margin-inline-start: auto; }

    .ep-details__body {
        padding: 0 1rem 1rem;
        display: flex;
        flex-direction: column;
        gap: .75rem;
    }

    .ep-exception {
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        font-size: .8125rem;
        line-height: 1.55;
    }

    .ep-exception__class { color: var(--ep-primary-text); font-weight: 650; overflow-wrap: anywhere; }
    .ep-exception__message { color: var(--ep-text); overflow-wrap: anywhere; }
    .ep-exception__location { color: var(--ep-text-subtle); }

    .ep-trace {
        margin: 0;
        padding: .75rem;
        border-radius: .5rem;
        background-color: var(--ep-code-bg);
        border: 1px solid var(--ep-border);
        overflow-x: auto;
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        font-size: .75rem;
        line-height: 1.7;
        color: var(--ep-text-muted);
        max-height: 20rem;
        overflow-y: auto;
    }

    .ep-trace div { white-space: pre; }
    .ep-trace .ep-trace--vendor { color: var(--ep-text-subtle); opacity: .75; }
    .ep-trace .ep-trace__index { color: var(--ep-text-subtle); }

    /* -------------------------------------------------------------- support */

    .ep-support {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .375rem;
        font-size: .875rem;
        color: var(--ep-text-subtle);
        text-align: center;
        justify-content: center;
    }

    .ep-support a { color: var(--ep-primary-text); text-decoration: none; font-weight: 550; }
    .ep-support a:hover { text-decoration: underline; }
    .ep-support a:focus-visible { outline: 2px solid var(--ep-primary-ring); outline-offset: 2px; border-radius: .25rem; }

    .ep-sr-only {
        position: absolute;
        width: 1px; height: 1px;
        padding: 0; margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    @media (max-width: 30rem) {
        .ep-actions .ep-btn { flex: 1 1 100%; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: .01ms !important;
        }
    }

    @media print {
        body { background: #fff; color: #000; padding: 0; }
        .ep-card { border: none; box-shadow: none; }
        .ep-actions, button.ep-chip { display: none; }
        .ep-details { border: 1px solid #ccc; }
        .ep-details__body { display: block !important; }
    }
</style>
