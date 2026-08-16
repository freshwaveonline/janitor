{{--
    Inline stylesheet.

    Deliberately not a published asset: an error page has to render when the
    asset pipeline, the CDN or the network is exactly what is broken. Every
    colour comes from a custom property so the whole surface follows the
    configured primary colour and the visitor's colour scheme.
--}}
@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    $theme = $error->theme;
    $palette = $error->palette;
@endphp
<style>
    :root {
        color-scheme: {{ $theme->colorScheme() }};
@if ($theme !== \FreshwaveOnline\Janitor\Enums\Theme::Dark)
{!! $palette->declarations('light', '        ') !!}
@else
{!! $palette->declarations('dark', '        ') !!}
@endif
    }

@if ($theme === \FreshwaveOnline\Janitor\Enums\Theme::Auto)
    @media (prefers-color-scheme: dark) {
        :root:not([data-jn-theme="light"]) {
{!! $palette->declarations('dark', '            ') !!}
        }
    }

    [data-jn-theme="dark"] {
{!! $palette->declarations('dark', '        ') !!}
    }

    [data-jn-theme="light"] {
{!! $palette->declarations('light', '        ') !!}
    }

    @media (prefers-color-scheme: dark) {
        :root:not([data-jn-theme="light"]) .jn-brand--light { display: none; }
        :root:not([data-jn-theme="light"]) .jn-brand--dark { display: block; }
    }

    [data-jn-theme="dark"] .jn-brand--light { display: none; }
    [data-jn-theme="dark"] .jn-brand--dark { display: block; }
    [data-jn-theme="light"] .jn-brand--light { display: block; }
    [data-jn-theme="light"] .jn-brand--dark { display: none; }
@elseif ($theme === \FreshwaveOnline\Janitor\Enums\Theme::Dark)
    .jn-brand--light { display: none; }
    .jn-brand--dark { display: block; }
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
        background-color: var(--jn-bg);
        background-image:
            radial-gradient(70rem 40rem at 50% -20%, var(--jn-primary-soft), transparent 70%);
        color: var(--jn-text);
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
        font-size: 16px;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
    }

    .jn-shell {
        width: 100%;
        max-width: 40rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* ---------------------------------------------------------------- brand */

    .jn-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .625rem;
        min-height: 2rem;
        color: var(--jn-text-muted);
        font-size: .9375rem;
        font-weight: 600;
        letter-spacing: -.01em;
        text-decoration: none;
    }

    .jn-brand img { display: block; width: auto; max-width: 12rem; }
    .jn-brand--dark { display: none; }

    /* ----------------------------------------------------------------- card */

    .jn-card {
        background-color: var(--jn-surface);
        border: 1px solid var(--jn-border);
        border-radius: 1rem;
        box-shadow: var(--jn-shadow);
        padding: clamp(1.5rem, 5vw, 2.5rem);
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .jn-header { display: flex; gap: 1rem; align-items: flex-start; }

    .jn-emblem {
        flex: 0 0 auto;
        width: 3rem;
        height: 3rem;
        display: grid;
        place-items: center;
        border-radius: .75rem;
        background-color: var(--jn-primary-soft);
        border: 1px solid var(--jn-primary-border);
        color: var(--jn-primary-text);
    }

    .jn-emblem svg { width: 1.5rem; height: 1.5rem; }

    .jn-heading { display: flex; flex-direction: column; gap: .375rem; min-width: 0; }

    .jn-status {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--jn-text-subtle);
    }

    .jn-status b { color: var(--jn-text-muted); font-weight: 700; letter-spacing: .04em; }

    .jn-title {
        margin: 0;
        font-size: clamp(1.375rem, 1.1rem + 1.2vw, 1.75rem);
        font-weight: 650;
        line-height: 1.25;
        letter-spacing: -.02em;
        color: var(--jn-text);
        text-wrap: balance;
    }

    .jn-lead { margin: 0; color: var(--jn-text-muted); font-size: 1rem; text-wrap: pretty; }

    /* --------------------------------------------------------------- blocks */

    .jn-block {
        border: 1px solid var(--jn-border);
        border-radius: .75rem;
        background-color: var(--jn-surface-muted);
        padding: 1rem 1.125rem;
        display: flex;
        gap: .875rem;
    }

    .jn-block__icon { flex: 0 0 auto; color: var(--jn-text-subtle); margin-top: .1875rem; }
    .jn-block__icon svg { width: 1.125rem; height: 1.125rem; }
    .jn-block__body { display: flex; flex-direction: column; gap: .375rem; min-width: 0; }

    .jn-block__label {
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--jn-text-subtle);
    }

    .jn-block p { margin: 0; color: var(--jn-text-muted); font-size: .9375rem; }
    .jn-block p + p { color: var(--jn-text-subtle); }

    .jn-block--retry {
        background-color: var(--jn-primary-soft);
        border-color: var(--jn-primary-border);
    }

    .jn-block--retry .jn-block__icon { color: var(--jn-primary-text); }
    .jn-block--retry .jn-block__label { color: var(--jn-primary-text); }
    .jn-block--retry p { color: var(--jn-text); }

    .jn-countdown {
        font-variant-numeric: tabular-nums;
        font-weight: 650;
        color: var(--jn-primary-text);
    }

    /* ---------------------------------------------------------- suggestions */

    .jn-section { display: flex; flex-direction: column; gap: .75rem; }

    .jn-section__title {
        margin: 0;
        font-size: .8125rem;
        font-weight: 650;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--jn-text-subtle);
    }

    .jn-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .625rem; }

    .jn-list li {
        display: flex;
        gap: .625rem;
        align-items: flex-start;
        color: var(--jn-text-muted);
        font-size: .9375rem;
    }

    .jn-list svg { flex: 0 0 auto; width: 1.125rem; height: 1.125rem; margin-top: .1875rem; color: var(--jn-primary-text); }

    /* -------------------------------------------------------------- actions */

    .jn-actions { display: flex; flex-wrap: wrap; gap: .625rem; }

    .jn-btn {
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

    .jn-btn svg { width: 1.125rem; height: 1.125rem; flex: 0 0 auto; }
    .jn-btn:active { transform: translateY(1px); }

    .jn-btn:focus-visible {
        outline: 2px solid var(--jn-primary-ring);
        outline-offset: 2px;
    }

    .jn-btn--primary {
        background-color: var(--jn-primary);
        border-color: var(--jn-primary);
        color: var(--jn-primary-contrast);
        box-shadow: var(--jn-shadow-sm);
    }

    .jn-btn--primary:hover { background-color: var(--jn-primary-hover); border-color: var(--jn-primary-hover); }
    .jn-btn--primary:active { background-color: var(--jn-primary-active); }

    .jn-btn--secondary {
        background-color: var(--jn-surface);
        border-color: var(--jn-border-strong);
        color: var(--jn-text);
    }

    .jn-btn--secondary:hover { background-color: var(--jn-surface-muted); border-color: var(--jn-text-subtle); }

    .jn-btn--ghost { background-color: transparent; color: var(--jn-text-muted); }
    .jn-btn--ghost:hover { background-color: var(--jn-surface-sunken); color: var(--jn-text); }

    .jn-btn[disabled], .jn-btn[aria-disabled="true"] {
        opacity: .55;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* ----------------------------------------------------------------- meta */

    .jn-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--jn-border);
    }

    .jn-chip {
        display: inline-flex;
        align-items: center;
        gap: .4375rem;
        padding: .375rem .625rem;
        border-radius: .5rem;
        border: 1px solid var(--jn-border);
        background-color: var(--jn-surface-muted);
        color: var(--jn-text-muted);
        font-size: .75rem;
        line-height: 1.4;
        font: inherit;
        font-size: .75rem;
        cursor: default;
    }

    .jn-chip svg { width: .875rem; height: .875rem; color: var(--jn-text-subtle); flex: 0 0 auto; }
    .jn-chip__label { color: var(--jn-text-subtle); }

    .jn-chip__value {
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        font-weight: 600;
        color: var(--jn-text);
        letter-spacing: .01em;
        overflow-wrap: anywhere;
    }

    button.jn-chip { cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
    button.jn-chip:hover { border-color: var(--jn-primary-border); background-color: var(--jn-primary-soft); }
    button.jn-chip:focus-visible { outline: 2px solid var(--jn-primary-ring); outline-offset: 2px; }
    button.jn-chip[data-copied="true"] { border-color: var(--jn-primary-border); background-color: var(--jn-primary-soft); }
    button.jn-chip[data-copied="true"] .jn-chip__label { color: var(--jn-primary-text); }

    /* -------------------------------------------------------------- details */

    .jn-details {
        border: 1px solid var(--jn-border);
        border-radius: .75rem;
        background-color: var(--jn-surface-sunken);
        overflow: hidden;
    }

    .jn-details > summary {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .75rem 1rem;
        cursor: pointer;
        list-style: none;
        color: var(--jn-text-muted);
        font-size: .8125rem;
        font-weight: 600;
        user-select: none;
    }

    .jn-details > summary::-webkit-details-marker { display: none; }
    .jn-details > summary:focus-visible { outline: 2px solid var(--jn-primary-ring); outline-offset: -2px; }
    .jn-details > summary svg { width: 1rem; height: 1rem; transition: transform .15s ease; }
    .jn-details[open] > summary .jn-details__chevron { transform: rotate(180deg); }
    .jn-details__spacer { margin-inline-start: auto; }

    .jn-details__body {
        padding: 0 1rem 1rem;
        display: flex;
        flex-direction: column;
        gap: .75rem;
    }

    .jn-exception {
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        font-size: .8125rem;
        line-height: 1.55;
    }

    .jn-exception__class { color: var(--jn-primary-text); font-weight: 650; overflow-wrap: anywhere; }
    .jn-exception__message { color: var(--jn-text); overflow-wrap: anywhere; }
    .jn-exception__location { color: var(--jn-text-subtle); }

    .jn-trace {
        margin: 0;
        padding: .75rem;
        border-radius: .5rem;
        background-color: var(--jn-code-bg);
        border: 1px solid var(--jn-border);
        overflow-x: auto;
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        font-size: .75rem;
        line-height: 1.7;
        color: var(--jn-text-muted);
        max-height: 20rem;
        overflow-y: auto;
    }

    .jn-trace div { white-space: pre; }
    .jn-trace .jn-trace--vendor { color: var(--jn-text-subtle); opacity: .75; }
    .jn-trace .jn-trace__index { color: var(--jn-text-subtle); }

    /* -------------------------------------------------------------- support */

    .jn-support {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .375rem;
        font-size: .875rem;
        color: var(--jn-text-subtle);
        text-align: center;
        justify-content: center;
    }

    .jn-support a { color: var(--jn-primary-text); text-decoration: none; font-weight: 550; }
    .jn-support a:hover { text-decoration: underline; }
    .jn-support a:focus-visible { outline: 2px solid var(--jn-primary-ring); outline-offset: 2px; border-radius: .25rem; }

    .jn-sr-only {
        position: absolute;
        width: 1px; height: 1px;
        padding: 0; margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    @media (max-width: 30rem) {
        .jn-actions .jn-btn { flex: 1 1 100%; }
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
        .jn-card { border: none; box-shadow: none; }
        .jn-actions, button.jn-chip { display: none; }
        .jn-details { border: 1px solid #ccc; }
        .jn-details__body { display: block !important; }
    }
</style>
