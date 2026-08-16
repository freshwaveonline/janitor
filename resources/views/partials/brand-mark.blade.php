@php
    /** @var \Vvdboogaard\ErrorPages\Data\ErrorContext $error */
    $name = $error->brand('name');
    $logo = $error->brand('logo');
    $logoDark = $error->brand('logo_dark');
    $height = (int) $error->brand('logo_height', 32);
    $showName = (bool) $error->brand('show_name_beside_logo', false);
@endphp

@if ($logo)
    <img src="{{ $logo }}"
         alt="{{ $name ?? '' }}"
         height="{{ $height }}"
         style="height: {{ $height }}px"
         @class(['ep-brand--light' => (bool) $logoDark])>

    @if ($logoDark)
        {{-- Swapped by the theme rules in the inline stylesheet. --}}
        <img src="{{ $logoDark }}"
             alt="{{ $name ?? '' }}"
             height="{{ $height }}"
             style="height: {{ $height }}px"
             class="ep-brand--dark">
    @endif
@endif

@if ($name && (! $logo || $showName))
    <span>{{ $name }}</span>
@endif
