{{-- Pastel statistic card (same as the eCommerce dashboards): [label .. icon] [big value] [sub text].
     Value is filled by dashboard.js through data-card when $card is given. --}}
@props(['label', 'card' => null, 'value' => null, 'icon' => 'fa-solid fa-chart-line', 'bg' => '#bfd7fd', 'color' => '#1d4ed8', 'sub' => null, 'href' => null, 'tone' => null])
@php
    $color = $tone ?? $color;
    $iconClass = str_contains($icon, 'fa-') ? $icon : 'bi bi-'.$icon;
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="st-card" style="--st-bg: {{ $bg }}; --st-c: {{ $color }}">
    <span class="st-l">{{ $label }}</span>
    <span class="st-ic"><i class="{{ $iconClass }}"></i></span>
    <span class="st-v" @if($card) data-card="{{ $card }}" @endif>{{ $value ?? '…' }}</span>
    <span class="st-s">{{ $sub ?? "\u{00A0}" }}</span>
</{{ $tag }}>
