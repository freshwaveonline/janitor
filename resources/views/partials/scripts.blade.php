{{--
    Page behaviour: copy-to-clipboard, the retry countdown and the history /
    reload buttons. Inline and dependency-free for the same reason the CSS is.
--}}
<script>
    (function () {
        'use strict';

        /* ------------------------------------------------------ clipboard */

        function writeToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }

            // execCommand is deprecated but is the only option left on plain
            // HTTP, which is exactly where a broken staging box tends to live.
            return new Promise(function (resolve, reject) {
                var field = document.createElement('textarea');
                field.value = text;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.select();

                try {
                    document.execCommand('copy') ? resolve() : reject();
                } catch (error) {
                    reject(error);
                } finally {
                    document.body.removeChild(field);
                }
            });
        }

        function flashCopied(button) {
            var label = button.querySelector('.ep-chip__label') || button.querySelector('span');
            var confirmation = button.getAttribute('data-ep-copy-label') || 'Copied';

            if (!label || button.hasAttribute('data-copied')) {
                return;
            }

            var original = label.textContent;
            label.textContent = confirmation;
            button.setAttribute('data-copied', 'true');

            window.setTimeout(function () {
                label.textContent = original;
                button.removeAttribute('data-copied');
            }, 2000);
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-ep-copy], [data-ep-copy-from]');

            if (!trigger) {
                return;
            }

            var text = trigger.getAttribute('data-ep-copy');

            if (text === null) {
                var source = document.querySelector(trigger.getAttribute('data-ep-copy-from'));

                if (!source) {
                    return;
                }

                try {
                    text = JSON.parse(source.textContent);
                } catch (error) {
                    text = source.textContent;
                }
            }

            event.preventDefault();

            writeToClipboard(text).then(function () {
                flashCopied(trigger);
            }).catch(function () {
                /* Clipboard blocked by policy; the text is on screen anyway. */
            });
        });

        /* -------------------------------------------------------- actions */

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-ep-action]');

            if (!trigger) {
                return;
            }

            event.preventDefault();
            var action = trigger.getAttribute('data-ep-action');

            if (action === 'reload') {
                window.location.reload();

                return;
            }

            if (action === 'back') {
                // A brand new tab has nothing to go back to; the home button is
                // a better destination than a dead click.
                if (window.history.length > 1) {
                    window.history.back();

                    return;
                }

                var home = document.querySelector('[data-ep-home]');

                if (home) {
                    window.location.href = home.getAttribute('href');
                }
            }
        });

        /* ------------------------------------------------------- countdown */

        function formatRemaining(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var rest = seconds % 60;
            var pad = function (value) { return value < 10 ? '0' + value : String(value); };

            return hours > 0
                ? hours + ':' + pad(minutes) + ':' + pad(rest)
                : pad(minutes) + ':' + pad(rest);
        }

        function holdRetryButtons() {
            document.querySelectorAll('[data-ep-wait-for-retry]').forEach(function (button) {
                if (button.tagName === 'BUTTON') {
                    button.setAttribute('disabled', 'disabled');
                }

                button.setAttribute('aria-disabled', 'true');
            });
        }

        function releaseRetryButtons() {
            document.querySelectorAll('[data-ep-wait-for-retry]').forEach(function (button) {
                button.removeAttribute('disabled');
                button.removeAttribute('aria-disabled');
                button.removeAttribute('data-ep-wait-for-retry');
            });
        }

        document.querySelectorAll('[data-ep-countdown]').forEach(function (element) {
            var target = Date.parse(element.getAttribute('data-ep-countdown'));

            if (isNaN(target)) {
                return;
            }

            var doneText = element.getAttribute('data-ep-countdown-now') || '';
            var shouldReload = element.getAttribute('data-ep-countdown-reload') === 'true';

            var tick = function () {
                var remaining = Math.max(0, Math.round((target - Date.now()) / 1000));

                if (remaining <= 0) {
                    element.textContent = doneText;
                    releaseRetryButtons();
                    window.clearInterval(timer);

                    if (shouldReload) {
                        window.location.reload();
                    }

                    return;
                }

                holdRetryButtons();
                element.textContent = '(' + formatRemaining(remaining) + ')';
            };

            var timer = window.setInterval(tick, 1000);
            tick();
        });
    })();
</script>
