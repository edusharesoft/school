@extends('layouts.guest')
@section('title', __('Too many attempts'))

@section('content')
<div class="card shadow-sm text-center">
    <div class="card-body p-5">
        <i class="bi bi-speedometer display-5 text-brand"></i>
        <div class="display-6 fw-bold mt-2">429</div>
        <h1 class="h5">{{ __('Too many attempts') }}</h1>
        <p class="text-body-secondary">{{ $exception?->getMessage() ?: __('Please wait a minute before trying again.') }}</p>
        <a href="{{ url('/') }}" class="btn btn-brand">{{ __('Go to home') }}</a>
    </div>
</div>
@endsection
