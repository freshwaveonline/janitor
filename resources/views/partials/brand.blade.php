@php
    /** @var \FreshwaveOnline\Janitor\Data\ErrorContext $error */
    $brandHome = $error->branding->homeUrl;
@endphp

@if ($error->branding->hasMark())
    @if ($brandHome)
        <a class="jn-brand" href="{{ $brandHome }}">@include('janitor::partials.brand-mark')</a>
    @else
        <div class="jn-brand">@include('janitor::partials.brand-mark')</div>
    @endif
@endif
