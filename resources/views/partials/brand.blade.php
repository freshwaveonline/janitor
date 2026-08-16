@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    $brandHome = $error->branding->homeUrl;
@endphp

@if ($error->branding->hasMark())
    @if ($brandHome)
        <a class="ep-brand" href="{{ $brandHome }}">@include('error-pages::partials.brand-mark')</a>
    @else
        <div class="ep-brand">@include('error-pages::partials.brand-mark')</div>
    @endif
@endif
