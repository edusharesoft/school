{{--
    Boxed sidebar menu with colourful icons and collapsible groups (same pattern as the eCommerce platform).
    $items: list of
      ['sec' => 'Heading']                                                          section title
      ['label', 'icon', 'color', 'url', 'active', 'badge'?, 'soon'?, 'blank'?]      link  ('soon' = coming in a later part)
      ['label', 'icon', 'color', 'children' => [links…]]                            group (opens when a child is active)
    Items with 'show' => false are skipped; a group without visible children is skipped;
    a section title with no visible item after it is skipped.
--}}
@php
    $visible = fn ($i) => ($i['show'] ?? true) !== false
        && (empty($i['children']) || collect($i['children'])->contains(fn ($k) => ($k['show'] ?? true) !== false));
    $items = array_values(array_filter($items, $visible));
    $items = array_values(array_filter($items, fn ($i, $n) => ! isset($i['sec']) || (isset($items[$n + 1]) && ! isset($items[$n + 1]['sec'])), ARRAY_FILTER_USE_BOTH));
@endphp
@foreach ($items as $n => $item)
    @if (isset($item['sec']))
        <div class="sec">{{ $item['sec'] }}</div>
    @elseif (! empty($item['children']))
        @php
            $kids = array_values(array_filter($item['children'], fn ($k) => ($k['show'] ?? true) !== false));
            $open = collect($kids)->contains(fn ($k) => ! empty($k['active']));
            $gid = 'pmg'.$n.'_'.substr(md5($item['label']), 0, 6);
            $badgeSum = collect($kids)->sum(fn ($k) => (int) ($k['badge'] ?? 0));
        @endphp
        <button type="button" @class(['pm-item', 'open' => $open]) data-bs-toggle="collapse" data-bs-target="#{{ $gid }}" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $gid }}" title="{{ $item['label'] }}">
            <span class="pm-ic" style="background:{{ $item['color'] }}1f;color:{{ $item['color'] }}"><i class="{{ $item['icon'] }}"></i></span>
            <span class="pm-label">{{ $item['label'] }}</span>
            @if ($badgeSum)<span class="badge bg-warning text-dark">{{ $badgeSum }}</span>@endif
            <i class="fa-solid fa-chevron-right pm-chev"></i>
        </button>
        <div id="{{ $gid }}" @class(['collapse', 'pm-sub', 'show' => $open])>
            @foreach ($kids as $k)
                @include('partials.panel-menu-link', ['link' => $k, 'color' => $k['color'] ?? $item['color']])
            @endforeach
        </div>
    @else
        @include('partials.panel-menu-link', ['link' => $item, 'color' => $item['color']])
    @endif
@endforeach
