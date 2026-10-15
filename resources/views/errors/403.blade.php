@extends('layouts.guest')
@section('title', __('Access denied'))

@section('content')
<div class="card shadow-sm text-center">
    <div class="card-body p-5">
        <i class="bi bi-shield-lock display-5 text-brand"></i>
        <div class="display-6 fw-bold mt-2">403</div>
        <h1 class="h5">{{ __('Access denied') }}</h1>
        <p class="text-body-secondary">{{ $exception?->getMessage() ?: __('You do not have permission to view this page.') }}</p>
        <a href="{{ url('/') }}" class="btn btn-brand">{{ __('Go to home') }}</a>
    </div>
</div>
@endsection
