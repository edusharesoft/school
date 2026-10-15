{{-- Public website of one branch (its own address). Same visual language as the institution site (layouts/website). --}}
@extends('layouts.base')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/website.css') }}?v=4">
@endpush

@section('body-class', 'site')

@section('body')
@use('App\Support\Website')
@php
    $b = $branch ?? $currentBranch;
    $siteName = $b->displayName();
    $logo = Website::img($b->logo_path) ?: Website::img($b->institution?->logo_path);
    $overlay = trim($__env->yieldContent('header-overlay')) !== '';
    $home = route('branch.home');
    $isBn = app()->getLocale() === 'bn';
    $nav = $nav ?? app(\App\Services\Website\BranchSite::class)->navigation($b);
    $site = $site ?? [];
    $menuBefore = $nav['menu']->filter(fn ($p) => $p->sort_order < 40);
    $menuAfter = $nav['menu']->filter(fn ($p) => $p->sort_order >= 40);
@endphp

<div class="site-top {{ $overlay ? 'is-overlay' : '' }}">
    <div class="site-wrap site-top-row">
        <div class="site-top-info">
            @if($b->phone)<span><i class="fa-solid fa-phone-volume"></i> {{ $b->phone }}</span>@endif
            @if($b->address)<span><i class="fa-solid fa-location-dot"></i> {{ \Illuminate\Support\Str::limit($b->address, 40) }}</span>@endif
        </div>
        <div class="site-top-auth">
            @auth
                <a href="{{ route('home.redirect') }}">{{ __('Dashboard') }}</a>
            @else
                <a href="{{ route('login') }}">{{ __('Login') }}</a>
                @if(\App\Support\AuthTheme::registrationOpen())
                    <a href="{{ route('register') }}">{{ __('Registration') }}</a>
                @endif
            @endauth
        </div>
    </div>
</div>

<header class="site-header {{ $overlay ? 'is-overlay' : '' }}">
    <div class="site-wrap">
        <div class="site-bar">
            <a class="site-brand" href="{{ $home }}">
                @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span class="site-brand-text">{{ $siteName }}</span>@endif
            </a>
            <button class="site-burger" type="button" aria-label="{{ __('Toggle navigation') }}" aria-expanded="false" aria-controls="siteNav"
                    onclick="var n=document.getElementById('siteNav');var o=n.classList.toggle('open');this.setAttribute('aria-expanded',o)">
                <i class="fa-solid fa-bars"></i>
            </button>
            <nav id="siteNav" class="site-nav" aria-label="{{ __('Main') }}">
                <a href="{{ $home }}" class="{{ request()->routeIs('branch.home') ? 'active' : '' }}">{{ __('Home') }}</a>
                @foreach($menuBefore as $p)
                    <a href="{{ route('site.page', $p->slug) }}" class="{{ request()->is('page/'.$p->slug) ? 'active' : '' }}">{{ Website::label($p) }}</a>
                @endforeach
                <a href="{{ route('site.notices') }}" class="{{ request()->routeIs('site.notice*') ? 'active' : '' }}">{{ __('Notices') }}</a>
                @foreach($menuAfter as $p)
                    <a href="{{ route('site.page', $p->slug) }}" class="{{ request()->is('page/'.$p->slug) ? 'active' : '' }}">{{ Website::label($p) }}</a>
                @endforeach
                <a href="{{ route('locale.switch', $isBn ? 'en' : 'bn') }}" class="site-lang">{{ $isBn ? 'English' : 'বাংলা' }}</a>
            </nav>
            <a href="{{ route('admission.index') }}" class="site-apply">{{ __('Apply') }}</a>
        </div>
    </div>
</header>

<main id="main">@yield('content')</main>

@php($partners = collect($site['partners'] ?? [])->map(fn ($p) => Website::img($p))->filter())
@if($partners->isNotEmpty())
    <section class="site-partners" aria-label="{{ __('Partners') }}">
        <div class="site-wrap site-partners-row">
            @foreach($partners as $src)<img src="{{ $src }}" alt="" loading="lazy">@endforeach
        </div>
    </section>
@endif

<footer class="site-footer" id="contact">
    <div class="site-wrap site-footer-grid">
        <div class="site-footer-about">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}" class="site-footer-logo">@else<div class="site-footer-name">{{ $siteName }}</div>@endif
            @php($footText = $isBn && ! empty($site['footer']['text_bn']) ? $site['footer']['text_bn'] : ($site['footer']['text'] ?? null))
            @if($footText)<p>{{ $footText }}</p>@endif
            <ul class="site-footer-contact">
                @if($b->phone)<li><i class="fa-solid fa-phone"></i><span>{{ $b->phone }}</span></li>@endif
                @if($b->address)<li><i class="fa-solid fa-location-dot"></i><span>{{ $b->address }}</span></li>@endif
                @if($b->email)<li><i class="fa-solid fa-envelope"></i><a href="mailto:{{ $b->email }}">{{ $b->email }}</a></li>@endif
            </ul>
        </div>
        <div>
            <h4>{{ __('Quick Links') }}</h4>
            <ul class="site-links">
                @foreach($nav['quick'] as $p)<li><a href="{{ route('site.page', $p->slug) }}">{{ Website::label($p) }}</a></li>@endforeach
                <li><a href="{{ route('site.news') }}">{{ __('Update News') }}</a></li>
                <li><a href="{{ route('results.index') }}">{{ __('Results') }}</a></li>
            </ul>
        </div>
        <div>
            <h4>{{ __('Our Campus') }}</h4>
            <ul class="site-links">
                <li><a href="{{ route('admission.index') }}">{{ __('Application Form') }}</a></li>
                @foreach($nav['campus'] as $p)<li><a href="{{ route('site.page', $p->slug) }}">{{ Website::label($p) }}</a></li>@endforeach
            </ul>
        </div>
        <div id="newsletter">
            <h4>{{ __('Newsletter') }}</h4>
            <p class="small">{{ __('Subscribe our newsletter to get latest update and news') }}</p>
            @if(session('success') && request()->routeIs('branch.home'))<div class="site-flash" role="status">{{ session('success') }}</div>@endif
            <form method="POST" action="{{ route('site.newsletter') }}" class="site-newsletter">
                @csrf
                <div class="site-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <input type="email" name="email" required maxlength="191" placeholder="{{ __('Your Email') }}" aria-label="{{ __('Your Email') }}" value="{{ old('email') }}">
                @error('email')<div class="site-form-error">{{ $message }}</div>@enderror
                <button type="submit" class="site-btn site-btn-orange">{{ __('Subscribe Now') }} <i class="fa-regular fa-paper-plane"></i></button>
            </form>
        </div>
    </div>
    <div class="site-wrap site-footer-bottom">
        <span>&copy; {{ now()->year }} <b>{{ $siteName }}</b>. {{ __('All Rights Reserved') }}</span>
        <span class="site-social">
            @foreach(['facebook' => 'fa-facebook-f', 'instagram' => 'fa-instagram', 'youtube' => 'fa-youtube', 'linkedin' => 'fa-linkedin-in'] as $net => $icon)
                @if(! empty($site['social'][$net]))<a href="{{ $site['social'][$net] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $net }}"><i class="fa-brands {{ $icon }}"></i></a>@endif
            @endforeach
        </span>
    </div>
</footer>
@if(session('success') && ! request()->routeIs('branch.home'))
    <div class="site-toast" role="status" onclick="this.remove()">{{ session('success') }}</div>
@endif
@endsection
