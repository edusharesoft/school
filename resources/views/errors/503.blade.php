@extends('layouts.guest')
@section('title', __('Temporarily unavailable'))

@section('content')
<div class="card shadow-sm text-center">
    <div class="card-body p-5">
        <i class="bi bi-cone-striped display-5 text-brand"></i>
        <div class="display-6 fw-bold mt-2">503</div>
        <h1 class="h5">{{ __('Temporarily unavailable') }}</h1>
        <p class="text-body-secondary">{{ $exception?->getMessage() ?: __('This service is temporarily unavailable. Please try again later.') }}</p>
        <a href="{{ url('/') }}" class="btn btn-brand">{{ __('Go to home') }}</a>
    </div>
</div>
@endsection
