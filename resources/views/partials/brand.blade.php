@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    $brandHome = collect($error->actions())->firstWhere('key', 'home')?->url;
@endphp

@if ($error->brand('logo') || $error->brand('name'))
    @if ($brandHome)
        <a class="ep-brand" href="{{ $brandHome }}">@include('error-pages::partials.brand-mark')</a>
    @else
        <div class="ep-brand">@include('error-pages::partials.brand-mark')</div>
    @endif
@endif
