@extends('layouts.auth')
@section('title', __('Sign in'))
@section('heading', $isCentral ? __('Central administration sign-in') : __('Login To Your Account'))

@section('content')
<form method="POST" action="{{ route('login.store') }}" novalidate>
    @csrf
    <div @class(['au-field', 'is-invalid' => $errors->has('login')])>
        <span class="ic"><i class="fa-regular fa-user"></i></span>
        <input name="login" value="{{ old('login') }}" placeholder="{{ __('E-mail, phone or username') }}" aria-label="{{ __('E-mail, phone or username') }}"
               autocomplete="username" required autofocus>
    </div>
    @error('login')<div class="au-err">{{ $message }}</div>@enderror
    <div @class(['au-field', 'is-invalid' => $errors->has('password')])>
        <span class="ic"><i class="fa-solid fa-lock"></i></span>
        <input type="password" name="password" placeholder="{{ __('Enter Password') }}" aria-label="{{ __('Password') }}" autocomplete="current-password" required>
        <button type="button" class="eye" tabindex="-1" aria-label="{{ __('Show password') }}"><i class="fa-regular fa-eye-slash"></i></button>
    </div>
    @error('password')<div class="au-err">{{ $message }}</div>@enderror
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
        <label class="form-check-label" for="remember">{{ __('Keep me signed in') }}</label>
    </div>
    <button class="au-btn" type="submit">{{ __('Sign in') }}</button>
    <a href="{{ route('password.request') }}" class="au-btn-2">{{ __('Forgot your password?') }}</a>
</form>
@if ($isCentral && \App\Support\AuthTheme::registrationOpen())
    <div class="au-links">{{ __("Don't have an account for your institution?") }} <a href="{{ route('register') }}">{{ __('Register your institution') }}</a></div>
@endif
@endsection
