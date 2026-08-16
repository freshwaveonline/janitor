@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    $branding = $error->branding;
@endphp

@if ($branding->logo)
    <img src="{{ $branding->logo }}"
         alt="{{ $branding->name ?? '' }}"
         height="{{ $branding->logoHeight }}"
         style="height: {{ $branding->logoHeight }}px"
         @class(['ep-brand--light' => (bool) $branding->logoDark])>

    @if ($branding->logoDark)
        {{-- Swapped by the theme rules in the inline stylesheet. --}}
        <img src="{{ $branding->logoDark }}"
             alt="{{ $branding->name ?? '' }}"
             height="{{ $branding->logoHeight }}"
             style="height: {{ $branding->logoHeight }}px"
             class="ep-brand--dark">
    @endif
@endif

@if ($branding->name && (! $branding->logo || $branding->showNameBesideLogo))
    <span>{{ $branding->name }}</span>
@endif
