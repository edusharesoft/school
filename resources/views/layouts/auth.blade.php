{{--
    Login / registration / password pages (same design as the eCommerce platform).
    Child views set:  @section('title')  @section('heading')  @section('subheading')?  @section('content')
    optional:         @section('auth_kind', 'register')  → background + video of the registration page
                      @section('auth_size', 'wide')       → wider card (long forms)
                      @section('auth_video', 'off')       → no video on this page
--}}
@php
    $kind = trim($__env->yieldContent('auth_kind')) === 'register' ? 'register' : 'login';
    $size = trim($__env->yieldContent('auth_size'));
    $authBranch = $currentBranch ?? null;
    $logo = \App\Support\AuthTheme::logo($authBranch);
    $brandName = \App\Support\AuthTheme::name($authBranch);
    $bg = \App\Support\AuthTheme::background($kind);
    $video = trim($__env->yieldContent('auth_video')) === 'off' ? null : \App\Support\AuthTheme::video($kind);
    [$fPre, $fName] = \App\Support\AuthTheme::footerParts();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <title>@yield('title') — {{ $brandName }}</title>
</head>
<body class="auth-ui">
<div class="au-bg" style="--au-dim: {{ \App\Support\AuthTheme::dim() }};@if ($bg) --au-img: url('{{ $bg }}');@endif"></div>
<div class="au-top">
    @include('partials.lang-switch', ['class' => 'text-white'])
    <a href="{{ tenant()->isBranch() ? route('login') : route('website.home') }}"><i class="fa-solid fa-house"></i> {{ __('Home') }}</a>
</div>

<main class="au-wrap">
    <div @class(['au-card', 'wide' => $size === 'wide', 'no-media' => $size === 'wide' && ! $video, 'solo' => ! $video && $size !== 'wide'])>
        <div class="au-head">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $brandName }}" class="au-logo">
                @if ($authBranch)<div class="fw-semibold mt-1">{{ $brandName }}</div>@endif
            @else
                <div class="au-name"><i class="fa-solid fa-graduation-cap"></i> {{ $brandName }}</div>
            @endif
            <div class="au-title">@yield('heading')</div>
            @hasSection('subheading')<div class="au-sub">@yield('subheading')</div>@endif
        </div>

        @foreach (['success' => 'success', 'status' => 'success', 'warning' => 'warning'] as $key => $type)
            @if (session($key))
                <div class="alert alert-{{ $type }} alert-dismissible fade show small py-2" role="alert">
                    {{ session($key) }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
                </div>
            @endif
        @endforeach

        @if ($video)
            <div class="row g-3 align-items-stretch">
                <div class="col-md-6 order-2 order-md-1">
                    <button type="button" class="au-media" data-yt="{{ $video }}" aria-label="{{ __('Play video') }}">
                        <img src="https://i.ytimg.com/vi/{{ $video }}/hqdefault.jpg" alt="" loading="lazy">
                        <span class="play"></span>
                    </button>
                </div>
                <div class="col-md-6 order-1 order-md-2 au-form">@yield('content')</div>
            </div>
        @else
            <div class="au-form">@yield('content')</div>
        @endif
    </div>
    <div class="au-foot">{{ $fPre }}{{ $fPre !== '' ? ' : ' : '' }}<b>{{ $fName }}</b></div>
</main>
@stack('scripts')
</body>
</html>
