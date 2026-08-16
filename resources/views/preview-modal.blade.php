@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    /** @var array<string, mixed> $payload */
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $error->locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Pop-up preview — {{ $error->statusCode }}</title>
    @include('error-pages::partials.styles')
    <style>
        body { justify-content: flex-start; }
        .ep-shell { max-width: 40rem; }
    </style>
</head>
<body>
    <div class="ep-shell">
        <main class="ep-card">
            <div class="ep-heading">
                <p class="ep-status"><b>Pop-up preview</b></p>
                <h1 class="ep-title">{{ $error->statusCode }} — {{ $error->title }}</h1>
                <p class="ep-lead">
                    This is what a Livewire round-trip failure looks like. The page underneath keeps
                    its state; only the pop-up appears.
                </p>
            </div>

            <div class="ep-actions">
                <button type="button" class="ep-btn ep-btn--primary" id="ep-preview-show">Show pop-up</button>
                <a class="ep-btn ep-btn--secondary" href="?">Full page instead</a>
            </div>
        </main>
    </div>

    @include('error-pages::partials.livewire-script')

    <script>
        (function () {
            var payload = @json($payload);

            document.getElementById('ep-preview-show').addEventListener('click', function () {
                // The handler exposes no public API, so the preview drives it the
                // same way Livewire does: through a failed request payload.
                document.dispatchEvent(new CustomEvent('ep-preview', { detail: payload }));
            });
        })();
    </script>
</body>
</html>
