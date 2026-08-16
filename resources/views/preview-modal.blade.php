@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    /** @var array<string, mixed> $payload */
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $error->locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Pop-up preview — {{ $error->statusCode }}</title>
    @include('janitor::partials.styles')
    <style>
        body { justify-content: flex-start; }
        .jn-shell { max-width: 40rem; }
    </style>
</head>
<body>
    <div class="jn-shell">
        <main class="jn-card">
            <div class="jn-heading">
                <p class="jn-status"><b>Pop-up preview</b></p>
                <h1 class="jn-title">{{ $error->statusCode }} — {{ $error->title }}</h1>
                <p class="jn-lead">
                    This is what a Livewire round-trip failure looks like. The page underneath keeps
                    its state; only the pop-up appears.
                </p>
            </div>

            <div class="jn-actions">
                <button type="button" class="jn-btn jn-btn--primary" id="jn-preview-show">Show pop-up</button>
                <a class="jn-btn jn-btn--secondary" href="?">Full page instead</a>
            </div>
        </main>
    </div>

    @include('janitor::partials.livewire-script')

    <script>
        (function () {
            var payload = @json($payload);

            document.getElementById('jn-preview-show').addEventListener('click', function () {
                // The handler exposes no public API, so the preview drives it the
                // same way Livewire does: through a failed request payload.
                document.dispatchEvent(new CustomEvent('jn-preview', { detail: payload }));
            });
        })();
    </script>
</body>
</html>
