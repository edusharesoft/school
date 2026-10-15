{{-- Public pages of a branch (admission, result, certificate verification): branch logo + name, no login needed. --}}
@extends('layouts.base')

@section('body-class', 'panel-ui')

@section('body')
@php
    $b = $currentBranch;
    $logo = \App\Support\AuthTheme::logo($b);
@endphp
<nav class="navbar navbar-expand-md bg-body border-bottom sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('admission.index') }}">
            @if($logo)<img src="{{ $logo }}" alt="" style="height:38px">@else<span class="pm-ic" style="background:#6366f11f;color:#6366f1;width:38px;height:38px"><i class="fa-solid fa-school"></i></span>@endif
            <span class="lh-sm"><b>{{ $b?->displayName() }}</b><br><small class="text-body-secondary">{{ $b?->institution?->name }}</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pubNav" aria-controls="pubNav" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="pubNav">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
                <li class="nav-item"><a class="nav-link" href="{{ route('admission.index') }}"><i class="fa-solid fa-id-card me-1"></i>{{ __('Admission') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admission.status') }}"><i class="fa-solid fa-magnifying-glass me-1"></i>{{ __('Application status') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('results.index') }}"><i class="fa-solid fa-square-poll-vertical me-1"></i>{{ __('Results') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('certificates.verify') }}"><i class="fa-solid fa-certificate me-1"></i>{{ __('Verify certificate') }}</a></li>
                <li class="nav-item small">@include('partials.lang-switch')</li>
                <li class="nav-item"><a class="btn btn-brand btn-sm" href="{{ route('login') }}">{{ __('Sign in') }}</a></li>
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4" style="min-height:70vh">
    @include('partials.alerts')
    @yield('content')
</main>
<footer class="border-top py-3">
    <div class="container small text-body-secondary d-flex flex-wrap justify-content-between gap-2">
        <span>&copy; {{ now()->year }} {{ $b?->displayName() }} · {{ $b?->address }}</span>
        <span>{{ \App\Support\AuthTheme::footer() }}</span>
    </div>
</footer>
@endsection
