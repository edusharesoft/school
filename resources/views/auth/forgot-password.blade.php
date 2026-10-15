@extends('layouts.auth')
@section('title', __('Forgot your password?'))
@section('heading', __('Forgot your password?'))
@section('subheading', __('Enter your account e-mail and we will send you a reset link.'))
@section('auth_video', 'off')

@section('content')
<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf
    <div @class(['au-field', 'is-invalid' => $errors->has('email')])>
        <span class="ic"><i class="fa-regular fa-envelope"></i></span>
        <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('E-mail') }}" aria-label="{{ __('E-mail') }}" required autofocus>
    </div>
    @error('email')<div class="au-err">{{ $message }}</div>@enderror
    <button class="au-btn" type="submit"><i class="fa-solid fa-paper-plane"></i> {{ __('Send reset link') }}</button>
    <a href="{{ route('login') }}" class="au-btn-2"><i class="fa-solid fa-arrow-left"></i> {{ __('Back to sign in') }}</a>
</form>
<div class="au-note">{{ __('Students and parents can also ask the branch office to reset their password.') }}</div>
@endsection
