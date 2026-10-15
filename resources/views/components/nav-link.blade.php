@props(['route', 'icon' => 'circle', 'pattern' => null, 'active' => null])
@php($isActive = $active ?? request()->routeIs($pattern ?? $route))
<a href="{{ route($route) }}" {{ $attributes->class(['nav-link', 'active' => $isActive]) }} @if($isActive) aria-current="page" @endif>
    <i class="bi bi-{{ $icon }}"></i><span>{{ $slot }}</span>
</a>
