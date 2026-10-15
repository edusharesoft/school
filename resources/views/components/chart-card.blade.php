{{-- Colour-headed panel with a Chart.js canvas (loading / empty / error states handled by dashboard.js). --}}
@props(['title', 'chart', 'height' => 290, 'subtitle' => null, 'color' => 'blue', 'icon' => 'fa-solid fa-chart-line'])
<div class="dp {{ $color }}">
    <div class="dp-head">
        <h2><i class="{{ $icon }} me-1"></i> {{ $title }}</h2>
        @if($subtitle)<span class="sub">{{ $subtitle }}</span>@endif
    </div>
    <div class="dp-body">
        <div class="chart-box" data-chart="{{ $chart }}" style="height: {{ (int) $height }}px">
            <canvas id="chart-{{ $chart }}" role="img" aria-label="{{ $title }}"></canvas>
            <div class="chart-state"></div>
        </div>
    </div>
</div>
