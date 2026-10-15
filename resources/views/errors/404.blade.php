@extends('layouts.guest')
@section('title', __('Page not found'))

@section('content')
<div class="card shadow-sm text-center">
    <div class="card-body p-5">
        <i class="bi bi-compass display-5 text-brand"></i>
        <div class="display-6 fw-bold mt-2">404</div>
        <h1 class="h5">{{ __('Page not found') }}</h1>
        <p class="text-body-secondary">{{ __('The page or record you are looking for does not exist here.') }}</p>
        <a href="{{ url('/') }}" class="btn btn-brand">{{ __('Go to home') }}</a>
    </div>
</div>
@endsection
