@extends('layouts.auth')
@section('title', __('Choose a new password'))
@section('heading', __('Choose a new password'))
@section('auth_video', 'off')

@section('content')
<form method="POST" action="{{ route('password.update') }}" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div @class(['au-field', 'is-invalid' => $errors->has('email')])>
        <span class="ic"><i class="fa-regular fa-envelope"></i></span>
        <input type="email" name="email" value="{{ old('email', $email) }}" placeholder="{{ __('E-mail') }}" aria-label="{{ __('E-mail') }}" required>
    </div>
    @error('email')<div class="au-err">{{ $message }}</div>@enderror
    <div @class(['au-field', 'is-invalid' => $errors->has('password')])>
        <span class="ic"><i class="fa-solid fa-lock"></i></span>
        <input type="password" name="password" placeholder="{{ __('New password') }}" aria-label="{{ __('New password') }}" autocomplete="new-password" required>
        <button type="button" class="eye" tabindex="-1" aria-label="{{ __('Show password') }}"><i class="fa-regular fa-eye-slash"></i></button>
    </div>
    @error('password')<div class="au-err">{{ $message }}</div>@else<div class="au-help">{{ __('At least 8 characters with letters and numbers.') }}</div>@enderror
    <div class="au-field">
        <span class="ic"><i class="fa-solid fa-lock"></i></span>
        <input type="password" name="password_confirmation" placeholder="{{ __('Confirm password') }}" aria-label="{{ __('Confirm password') }}" autocomplete="new-password" required>
        <button type="button" class="eye" tabindex="-1" aria-label="{{ __('Show password') }}"><i class="fa-regular fa-eye-slash"></i></button>
    </div>
    <button class="au-btn" type="submit">{{ __('Update password') }}</button>
    <a href="{{ route('login') }}" class="au-btn-2">{{ __('Back to sign in') }}</a>
</form>
@endsection
