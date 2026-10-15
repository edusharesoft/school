{{--
    Authenticated back-office shell (Super Admin + Branch panels): white boxed sidebar with colourful
    icons, ☰ mini-sidebar toggle, topbar with language, notifications, user and logout.
    Child layouts pass: $menu (array for partials.panel-menu), $brand (array: name, sub, logo, icon, gradient, badge?, home)
--}}
@extends('layouts.base')

@section('body-class', 'panel-ui')

@section('body')
@php
    $user = auth()->user();
    $initial = $user?->initials() ?: 'U';
    $roleLabel = $user?->roles->first()?->label ?? $user?->roles->first()?->name;
@endphp
<aside class="mg-sidebar" id="sidebar" aria-label="{{ __('Main navigation') }}">
    <a href="{{ $brand['home'] }}" class="brand">
        @if ($brand['logo'])
            <img src="{{ $brand['logo'] }}" alt="" class="logo-img">
        @else
            <span class="logo-ic" style="background:{{ $brand['gradient'] }}"><i class="{{ $brand['icon'] }}"></i></span>
        @endif
        <span class="t"><b>{{ $brand['name'] }}</b><small>{{ $brand['sub'] }}</small></span>
        @if (! empty($brand['badge']))<span class="plan badge bg-success w-100 py-1">{{ $brand['badge'] }}</span>@endif
    </a>
    <nav>
        @include('partials.panel-menu', ['items' => $menu])
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit" class="pm-item" title="{{ __('Sign out') }}">
                <span class="pm-ic" style="background:#e11d481f;color:#e11d48"><i class="fa-solid fa-right-from-bracket"></i></span>
                <span class="pm-label">{{ __('Sign out') }}</span>
            </button>
        </form>
    </nav>
</aside>
<div class="sb-backdrop"></div>

<div class="mg-main">
    <header class="mg-topbar">
        <div class="d-flex align-items-center gap-2 min-w-0">
            <button type="button" class="burger" id="sbToggle" aria-label="{{ __('Open menu') }}" aria-controls="sidebar"><i class="fa-solid fa-bars"></i></button>
            <span class="page-title d-none d-sm-inline text-truncate">@yield('title', __('Dashboard'))</span>
        </div>
        <div class="tb-right">
            <div class="small">@include('partials.lang-switch')</div>
            <button type="button" class="border-0 bg-transparent d-none d-md-flex" data-theme-toggle aria-label="{{ __('Toggle dark mode') }}"><i class="fa-regular fa-moon fs-5"></i></button>
            @if (! empty($notificationsUrl))
                <a href="{{ $notificationsUrl }}" title="{{ __('Recent activity') }}" aria-label="{{ __('Recent activity') }}" class="d-none d-md-inline"><i class="fa-solid fa-clock-rotate-left fs-5"></i></a>
            @endif
            @if (tenant()->isBranch() && $user)
                @php
                    $inboxUnread = \App\Models\Communication\NotificationMessage::query()->inbox($user)->whereNull('read_at')->count();
                @endphp
                <a href="{{ route('inbox.index') }}" class="position-relative" title="{{ __('Notifications') }}" aria-label="{{ __('Notifications') }}"
                   data-inbox-bell data-url="{{ route('inbox.unread') }}" data-count="{{ $inboxUnread }}">
                    <i class="fa-regular fa-bell fs-5"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger {{ $inboxUnread ? '' : 'd-none' }}" style="font-size:.6rem" data-inbox-count>{{ $inboxUnread > 99 ? '99+' : $inboxUnread }}</span>
                </a>
            @endif
            <div class="dropdown p-0">
                <a href="#" class="tb-user h-100 px-3" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar">{{ $initial }}</span>
                    <span class="nm">{{ $user?->name }}<small>{{ $roleLabel }}</small></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                    <li><span class="dropdown-item-text text-body-secondary">{{ $user?->email }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('account.security') }}"><i class="fa-solid fa-shield-halved me-2"></i>{{ __('Account security') }}</a></li>
                    @if (tenant()->isBranch())
                        <li><a class="dropdown-item" href="{{ route('branch.switch-branch') }}"><i class="fa-solid fa-right-left me-2"></i>{{ __('Switch branch') }}</a></li>
                        @if ($user?->canUseCentral())
                            <li><form method="POST" action="{{ route('branch.switch-central') }}">@csrf
                                <button class="dropdown-item"><i class="fa-solid fa-crown me-2"></i>{{ __('Central administration') }}</button></form></li>
                        @endif
                    @else
                        <li><a class="dropdown-item" href="{{ route('central.switch-branch') }}"><i class="fa-solid fa-right-left me-2"></i>{{ __('Open a branch') }}</a></li>
                    @endif
                </ul>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="p-0">@csrf
                <button class="tb-logout" title="{{ __('Sign out') }}" aria-label="{{ __('Sign out') }}"><i class="fa-solid fa-right-from-bracket"></i></button>
            </form>
        </div>
    </header>

    <main class="mg-content" id="main">
        @include('partials.subscription-banner')
        @include('partials.alerts')
        @yield('content')
    </main>

    <footer class="mg-footer d-flex justify-content-between flex-wrap gap-2">
        <span>&copy; {{ now()->year }} {{ $user?->institution?->name ?? config('app.name') }} · {{ \App\Support\AuthTheme::footer() }}</span>
        <span>{{ __('Time zone') }}: {{ config('app.timezone') }} · {{ now()->format('d M Y, h:i A') }}</span>
    </footer>
</div>
@endsection
