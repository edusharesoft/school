@php($soon = ! empty($link['soon']))
<a href="{{ $soon ? '#' : $link['url'] }}" @class(['pm-item', 'active' => ! empty($link['active']), 'disabled' => $soon])
   @if (! empty($link['blank'])) target="_blank" rel="noopener" @endif @if ($soon) aria-disabled="true" tabindex="-1" @endif
   @if (! empty($link['active'])) aria-current="page" @endif title="{{ $link['label'] }}">
    <span class="pm-ic" style="background:{{ $color }}1f;color:{{ $color }}"><i class="{{ $link['icon'] }}"></i></span>
    <span class="pm-label">{{ $link['label'] }}</span>
    @if (! empty($link['badge']))<span class="badge bg-warning text-dark">{{ $link['badge'] }}</span>@endif
    @if ($soon)<span class="badge text-bg-light border">{{ $link['soon'] }}</span>@endif
</a>
