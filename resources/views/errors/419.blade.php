@extends('layouts.guest')
@section('title', __('Page expired'))

@section('content')
<div class="card shadow-sm text-center">
    <div class="card-body p-5">
        <i class="bi bi-hourglass-split display-5 text-brand"></i>
        <div class="display-6 fw-bold mt-2">419</div>
        <h1 class="h5">{{ __('Page expired') }}</h1>
        <p class="text-body-secondary">{{ $exception?->getMessage() ?: __('Your session expired. Please go back and try again.') }}</p>
        <a href="{{ url('/') }}" class="btn btn-brand">{{ __('Go to home') }}</a>
    </div>
</div>
@endsection
