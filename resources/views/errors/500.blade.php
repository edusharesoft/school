@extends('layouts.guest')
@section('title', __('Something went wrong'))

@section('content')
<div class="card shadow-sm text-center">
    <div class="card-body p-5">
        <i class="bi bi-bug display-5 text-brand"></i>
        <div class="display-6 fw-bold mt-2">500</div>
        <h1 class="h5">{{ __('Something went wrong') }}</h1>
        <p class="text-body-secondary">{{ __("An unexpected error occurred. The technical team has been notified.") }}</p>
        <a href="{{ url('/') }}" class="btn btn-brand">{{ __('Go to home') }}</a>
    </div>
</div>
@endsection
