@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    $locale = str_replace('_', '-', $error->locale);
    $direction = in_array(substr($locale, 0, 2), ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    $brandName = $error->branding->name;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('janitor.noindex') === true)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="color-scheme" content="{{ $error->theme->colorScheme() }}">
    <title>{{ $error->statusCode }} — {{ $error->title }}@if ($brandName) · {{ $brandName }}@endif</title>
    @include('janitor::partials.styles')
</head>
<body>
    <div class="jn-shell">
        @include('janitor::partials.brand')
        @include('janitor::partials.card')
    </div>

    @include('janitor::partials.scripts')
</body>
</html>
