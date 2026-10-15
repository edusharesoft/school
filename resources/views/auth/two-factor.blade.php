@extends('layouts.auth')
@section('title', __('Two-factor authentication'))
@section('heading', __('Two-factor authentication'))

@section('content')
<form method="POST" action="{{ route('two-factor.verify') }}" novalidate>
    @csrf
    <p class="small text-body-secondary">{{ __('Enter the 6-digit code from your authenticator app, or one of your recovery codes.') }}</p>
    <div @class(['au-field', 'is-invalid' => $errors->has('code')])>
        <span class="ic"><i class="fa-solid fa-shield-halved"></i></span>
        <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="{{ __('Code') }}" aria-label="{{ __('Code') }}" required autofocus maxlength="20">
    </div>
    @error('code')<div class="au-err">{{ $message }}</div>@enderror
    <button class="au-btn" type="submit">{{ __('Verify') }}</button>
</form>
<form method="POST" action="{{ route('two-factor.cancel') }}">@csrf<button class="au-btn-2 border-0 bg-transparent w-100">{{ __('Back to sign in') }}</button></form>
@endsection
