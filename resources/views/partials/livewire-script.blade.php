@php
    use Vvdboogaard\ErrorPages\Enums\LivewireErrorMode;
    use Vvdboogaard\ErrorPages\Enums\ModalPosition;
    use Vvdboogaard\ErrorPages\Enums\Theme;
    use Vvdboogaard\ErrorPages\Support\Palette;

    $theme = Theme::parse(config('error-pages.theme'));
    $palette = Palette::fromConfig((array) config('error-pages.colors', []), $theme);
    $position = ModalPosition::parse(config('error-pages.livewire.position'));
    $mode = LivewireErrorMode::parse(config('error-pages.livewire.mode'));

    $settings = [
        'mode' => $mode->value,
        'position' => $position->value,
        'dismissible' => config('error-pages.livewire.dismissible') === true,
        'autoDismiss' => (int) config('error-pages.livewire.auto_dismiss', 0),
        'interceptFetch' => config('error-pages.livewire.intercept_fetch') === true,
    ];
@endphp

{{--
    Livewire error handler.

    Livewire's own failure overlay drops an iframe of the raw response over the
    page. This replaces it with the same information the full page shows, in a
    pop-up that leaves the page state — and whatever the user had typed —
    completely intact.

    Everything is scoped under .ep-modal-root so nothing here can touch, or be
    touched by, the host application's stylesheet.
--}}
<style>
    .ep-modal-root {
        position: fixed;
        inset: 0;
        z-index: {{ (int) config('error-pages.livewire.z_index', 999999) }};
        display: flex;
        align-items: {{ $position->alignItems() }};
        justify-content: {{ $position->justifyContent() }};
        padding: 1rem;
        pointer-events: none;
        color-scheme: {{ $theme->colorScheme() }};
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
@if ($theme !== Theme::Dark)
{!! $palette->declarations('light', '        ') !!}
@else
{!! $palette->declarations('dark', '        ') !!}
@endif
    }

@if ($theme === Theme::Auto)
    @media (prefers-color-scheme: dark) {
        .ep-modal-root {
{!! $palette->declarations('dark', '            ') !!}
        }
    }
@endif

@if ($position->isDialog())
    .ep-modal-root::before {
        content: "";
        position: absolute;
        inset: 0;
        background: rgba(9, 9, 11, .45);
        pointer-events: auto;
    }
@endif

    .ep-modal {
        position: relative;
        pointer-events: auto;
        width: 100%;
        max-width: {{ config('error-pages.livewire.max_width', '26rem') }};
        background-color: var(--ep-surface);
        color: var(--ep-text);
        border: 1px solid var(--ep-border);
        border-radius: .875rem;
        box-shadow: var(--ep-shadow);
        padding: 1rem 1.125rem 1.125rem;
        display: flex;
        flex-direction: column;
        gap: .875rem;
        font-size: 14px;
        line-height: 1.55;
        animation: ep-modal-in .18s cubic-bezier(.16, 1, .3, 1);
    }

    @keyframes ep-modal-in {
        from { opacity: 0; transform: {{ $position->enterTransform() }}; }
        to { opacity: 1; transform: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .ep-modal { animation: none; }
    }

    .ep-modal__head { display: flex; gap: .75rem; align-items: flex-start; }

    .ep-modal__emblem {
        flex: 0 0 auto;
        width: 2.25rem;
        height: 2.25rem;
        display: grid;
        place-items: center;
        border-radius: .5rem;
        background-color: var(--ep-primary-soft);
        border: 1px solid var(--ep-primary-border);
        color: var(--ep-primary-text);
    }

    .ep-modal__emblem svg { width: 1.125rem; height: 1.125rem; }
    .ep-modal__heading { min-width: 0; display: flex; flex-direction: column; gap: .125rem; }

    .ep-modal__status {
        font-size: .6875rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--ep-text-subtle);
    }

    .ep-modal__title {
        margin: 0;
        font-size: .9375rem;
        font-weight: 650;
        letter-spacing: -.01em;
        color: var(--ep-text);
    }

    .ep-modal__message { margin: 0; color: var(--ep-text-muted); }

    .ep-modal__close {
        position: absolute;
        top: .625rem;
        inset-inline-end: .625rem;
        display: grid;
        place-items: center;
        width: 1.75rem;
        height: 1.75rem;
        border: 0;
        border-radius: .375rem;
        background: transparent;
        color: var(--ep-text-subtle);
        cursor: pointer;
    }

    .ep-modal__close:hover { background-color: var(--ep-surface-sunken); color: var(--ep-text); }
    .ep-modal__close:focus-visible { outline: 2px solid var(--ep-primary-ring); outline-offset: 2px; }
    .ep-modal__close svg { width: 1rem; height: 1rem; }

    .ep-modal__retry {
        display: flex;
        gap: .5rem;
        align-items: center;
        padding: .5rem .625rem;
        border-radius: .5rem;
        background-color: var(--ep-primary-soft);
        border: 1px solid var(--ep-primary-border);
        color: var(--ep-text);
        font-size: .8125rem;
    }

    .ep-modal__retry svg { width: 1rem; height: 1rem; color: var(--ep-primary-text); flex: 0 0 auto; }
    .ep-modal__retry b { font-variant-numeric: tabular-nums; color: var(--ep-primary-text); }

    .ep-modal__meta { display: flex; flex-wrap: wrap; gap: .375rem; }

    .ep-modal__chip {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        padding: .25rem .5rem;
        border-radius: .375rem;
        border: 1px solid var(--ep-border);
        background-color: var(--ep-surface-muted);
        color: var(--ep-text-subtle);
        font: inherit;
        font-size: .6875rem;
        cursor: pointer;
    }

    .ep-modal__chip:hover { border-color: var(--ep-primary-border); background-color: var(--ep-primary-soft); }
    .ep-modal__chip:focus-visible { outline: 2px solid var(--ep-primary-ring); outline-offset: 2px; }
    .ep-modal__chip svg { width: .75rem; height: .75rem; flex: 0 0 auto; }

    .ep-modal__chip b {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-weight: 600;
        color: var(--ep-text);
        overflow-wrap: anywhere;
    }

    .ep-modal__actions { display: flex; flex-wrap: wrap; gap: .5rem; }

    .ep-modal__btn {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        padding: .4375rem .75rem;
        border-radius: .5rem;
        border: 1px solid transparent;
        font: inherit;
        font-size: .8125rem;
        font-weight: 550;
        text-decoration: none;
        cursor: pointer;
    }

    .ep-modal__btn svg { width: 1rem; height: 1rem; flex: 0 0 auto; }
    .ep-modal__btn:focus-visible { outline: 2px solid var(--ep-primary-ring); outline-offset: 2px; }

    .ep-modal__btn--primary {
        background-color: var(--ep-primary);
        border-color: var(--ep-primary);
        color: var(--ep-primary-contrast);
    }

    .ep-modal__btn--primary:hover { background-color: var(--ep-primary-hover); }

    .ep-modal__btn--secondary {
        background-color: var(--ep-surface);
        border-color: var(--ep-border-strong);
        color: var(--ep-text);
    }

    .ep-modal__btn--secondary:hover { background-color: var(--ep-surface-muted); }

    .ep-modal__btn--ghost { background: transparent; color: var(--ep-text-muted); }
    .ep-modal__btn--ghost:hover { background-color: var(--ep-surface-sunken); color: var(--ep-text); }
    .ep-modal__btn[disabled] { opacity: .55; cursor: not-allowed; pointer-events: none; }

    .ep-modal__report {
        margin: 0;
        max-height: 9rem;
        overflow: auto;
        padding: .5rem;
        border-radius: .375rem;
        background-color: var(--ep-code-bg);
        border: 1px solid var(--ep-border);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .6875rem;
        line-height: 1.6;
        color: var(--ep-text-muted);
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
</style>

<script>
    (function () {
        'use strict';

        if (window.__errorPagesLivewire) {
            return;
        }

        window.__errorPagesLivewire = true;

        var settings = @json($settings);
        var SVG_NS = 'http://www.w3.org/2000/svg';
        var root = null;
        var dismissTimer = null;
        var countdownTimer = null;

        /* ----------------------------------------------------------- utils */

        function el(tag, className, text) {
            var node = document.createElement(tag);

            if (className) {
                node.className = className;
            }

            if (text !== undefined && text !== null) {
                node.textContent = text;
            }

            return node;
        }

        function icon(paths, name) {
            if (!paths || !paths[name]) {
                return null;
            }

            var svg = document.createElementNS(SVG_NS, 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', 'currentColor');
            svg.setAttribute('stroke-width', '1.5');
            svg.setAttribute('stroke-linecap', 'round');
            svg.setAttribute('stroke-linejoin', 'round');
            svg.setAttribute('aria-hidden', 'true');

            var path = document.createElementNS(SVG_NS, 'path');
            path.setAttribute('d', paths[name]);
            svg.appendChild(path);

            return svg;
        }

        function copy(text, button, confirmation) {
            var write = navigator.clipboard && window.isSecureContext
                ? navigator.clipboard.writeText(text)
                : Promise.reject();

            write.then(function () {
                var label = button.querySelector('span');

                if (!label) {
                    return;
                }

                var original = label.textContent;
                label.textContent = confirmation;
                window.setTimeout(function () { label.textContent = original; }, 2000);
            }).catch(function () { /* clipboard unavailable */ });
        }

        function formatRemaining(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var rest = seconds % 60;
            var pad = function (value) { return value < 10 ? '0' + value : String(value); };

            return hours > 0 ? hours + ':' + pad(minutes) + ':' + pad(rest) : pad(minutes) + ':' + pad(rest);
        }

        /* --------------------------------------------------------- teardown */

        function dismiss() {
            window.clearTimeout(dismissTimer);
            window.clearInterval(countdownTimer);

            if (root && root.parentNode) {
                root.parentNode.removeChild(root);
            }

            root = null;
            document.removeEventListener('keydown', onKeydown);
        }

        function onKeydown(event) {
            if (event.key === 'Escape' && settings.dismissible) {
                dismiss();
            }
        }

        /* ------------------------------------------------------------ build */

        function render(payload) {
            dismiss();

            var labels = payload.labels || {};
            var icons = payload.icons || {};

            root = el('div', 'ep-modal-root');
            root.setAttribute('role', 'alertdialog');
            root.setAttribute('aria-modal', 'false');
            root.setAttribute('aria-live', 'assertive');

            var modal = el('div', 'ep-modal');
            root.appendChild(modal);

            /* head */
            var head = el('div', 'ep-modal__head');
            var emblem = el('div', 'ep-modal__emblem');
            var statusIcon = icon(icons, payload.icon);

            if (statusIcon) {
                emblem.appendChild(statusIcon);
            }

            head.appendChild(emblem);

            var heading = el('div', 'ep-modal__heading');
            heading.appendChild(el('span', 'ep-modal__status', String(payload.status)));
            heading.appendChild(el('h2', 'ep-modal__title', payload.title));
            head.appendChild(heading);
            modal.appendChild(head);

            modal.appendChild(el('p', 'ep-modal__message', payload.message));

            if (payload.reason) {
                var reason = el('p', 'ep-modal__message', payload.reason);
                reason.style.fontSize = '.8125rem';
                modal.appendChild(reason);
            }

            /* close */
            if (settings.dismissible) {
                var close = el('button', 'ep-modal__close');
                close.type = 'button';
                close.setAttribute('aria-label', labels.dismiss || 'Dismiss');
                var closeIcon = icon(icons, 'x-mark');

                if (closeIcon) {
                    close.appendChild(closeIcon);
                }

                close.addEventListener('click', dismiss);
                modal.appendChild(close);
            }

            /* retry */
            if (payload.retry_at) {
                var retry = el('div', 'ep-modal__retry');
                var clock = icon(icons, 'clock');

                if (clock) {
                    retry.appendChild(clock);
                }

                var retryText = el('span', null, (labels.retry_in || 'Try again in') + ' ');
                var counter = el('b', null, '');
                retryText.appendChild(counter);
                retry.appendChild(retryText);
                modal.appendChild(retry);

                var target = Date.parse(payload.retry_at);

                var tick = function () {
                    var remaining = Math.max(0, Math.round((target - Date.now()) / 1000));
                    counter.textContent = formatRemaining(remaining);

                    if (remaining <= 0) {
                        window.clearInterval(countdownTimer);
                        modal.querySelectorAll('[data-ep-wait]').forEach(function (button) {
                            button.removeAttribute('disabled');
                        });
                    }
                };

                countdownTimer = window.setInterval(tick, 1000);
                tick();
            }

            /* meta */
            var meta = el('div', 'ep-modal__meta');

            [
                { value: payload.message_number, label: labels.message_number, name: 'hashtag' },
                { value: payload.request_id, label: labels.request_id, name: 'fingerprint' },
            ].forEach(function (entry) {
                if (!entry.value) {
                    return;
                }

                var chip = el('button', 'ep-modal__chip');
                chip.type = 'button';
                chip.title = entry.label;
                var chipIcon = icon(icons, entry.name);

                if (chipIcon) {
                    chip.appendChild(chipIcon);
                }

                chip.appendChild(el('span', null, entry.label));
                chip.appendChild(el('b', null, entry.value));
                chip.addEventListener('click', function () {
                    copy(entry.value, chip, labels.copied || 'Copied');
                });

                meta.appendChild(chip);
            });

            if (meta.childNodes.length) {
                modal.appendChild(meta);
            }

            /* technical report */
            if (payload.copy_report) {
                modal.appendChild(el('pre', 'ep-modal__report', payload.copy_report));
            }

            /* actions */
            var actions = el('div', 'ep-modal__actions');

            (payload.actions || []).forEach(function (action) {
                var isLink = action.behaviour === 'link' && action.url;
                var button = el(isLink ? 'a' : 'button', 'ep-modal__btn ep-modal__btn--' + action.style);

                if (isLink) {
                    button.href = action.url;

                    if (action.external) {
                        button.target = '_blank';
                        button.rel = 'noopener noreferrer';
                    }
                } else {
                    button.type = 'button';
                    button.addEventListener('click', function () {
                        if (action.behaviour === 'reload') {
                            window.location.reload();
                        } else if (action.behaviour === 'back') {
                            window.history.back();
                        }
                    });

                    if (action.waits_for_retry && payload.retry_in > 0) {
                        button.setAttribute('disabled', 'disabled');
                        button.setAttribute('data-ep-wait', '');
                    }
                }

                var actionIcon = icon(icons, action.icon);

                if (actionIcon) {
                    button.appendChild(actionIcon);
                }

                button.appendChild(el('span', null, action.label));
                actions.appendChild(button);
            });

            if (payload.copy_report) {
                var copyButton = el('button', 'ep-modal__btn ep-modal__btn--ghost');
                copyButton.type = 'button';
                var clipboardIcon = icon(icons, 'clipboard');

                if (clipboardIcon) {
                    copyButton.appendChild(clipboardIcon);
                }

                copyButton.appendChild(el('span', null, labels.copy || 'Copy'));
                copyButton.addEventListener('click', function () {
                    copy(payload.copy_report, copyButton, labels.copied || 'Copied');
                });

                actions.appendChild(copyButton);
            }

            if (actions.childNodes.length) {
                modal.appendChild(actions);
            }

            document.body.appendChild(root);
            document.addEventListener('keydown', onKeydown);

            if (settings.autoDismiss > 0) {
                dismissTimer = window.setTimeout(dismiss, settings.autoDismiss);
            }
        }

        /* ---------------------------------------------------------- handle */

        function payloadFrom(content) {
            if (typeof content !== 'string') {
                return null;
            }

            try {
                var parsed = JSON.parse(content);

                return parsed && parsed.errorPages ? parsed.errorPages : null;
            } catch (error) {
                return null;
            }
        }

        function takeOver(content) {
            var payload = payloadFrom(content);

            if (payload) {
                render(payload);

                return true;
            }

            // `page` mode returns the full HTML document; swapping it in gives a
            // real error page rather than Livewire's iframe overlay.
            if (settings.mode === 'page' && typeof content === 'string' && content.indexOf('<html') !== -1) {
                document.open();
                document.write(content);
                document.close();

                return true;
            }

            return false;
        }

        // Used by the preview route to render the pop-up on demand. Inert in
        // production: nothing else dispatches this event.
        document.addEventListener('ep-preview', function (event) {
            if (event.detail) {
                render(event.detail);
            }
        });

        document.addEventListener('livewire:init', function () {
            if (!window.Livewire || settings.mode === 'disabled') {
                return;
            }

            window.Livewire.hook('request', function (context) {
                if (typeof context.fail !== 'function') {
                    return;
                }

                context.fail(function (failure) {
                    if (takeOver(failure.content) && typeof failure.preventDefault === 'function') {
                        failure.preventDefault();
                    }
                });
            });
        });

        /* Plain fetch/XHR failures, for Alpine and hand-written scripts. */
        if (settings.interceptFetch && window.fetch) {
            var original = window.fetch;

            window.fetch = function () {
                return original.apply(this, arguments).then(function (response) {
                    if (response.ok || response.status < 400) {
                        return response;
                    }

                    var type = response.headers.get('content-type') || '';

                    if (type.indexOf('json') === -1) {
                        return response;
                    }

                    // Read from a clone so the caller still gets an unconsumed body.
                    response.clone().text().then(function (text) {
                        var payload = payloadFrom(text);

                        if (payload) {
                            render(payload);
                        }
                    }).catch(function () {});

                    return response;
                });
            };
        }
    })();
</script>
