@extends('layouts.base')

@section('body')
    <div class="auth-wrap">
        <div class="auth-card">
            @yield('content')
            <div class="text-center mt-3 small">
                <a href="{{ route('locale.switch', 'en') }}" class="text-decoration-none {{ app()->getLocale() === 'en' ? 'fw-semibold' : 'text-body-secondary' }}">English</a>
                <span class="text-body-secondary mx-1">|</span>
                <a href="{{ route('locale.switch', 'bn') }}" class="text-decoration-none bn {{ app()->getLocale() === 'bn' ? 'fw-semibold' : 'text-body-secondary' }}">বাংলা</a>
            </div>
        </div>
    </div>
@endsection
