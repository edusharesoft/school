@extends(tenant()->isCentral() ? 'layouts.central' : (in_array($user->portal(), ['teacher', 'student', 'parent'], true) ? 'layouts.portal' : 'layouts.branch'))
@section('title', __('Account security'))

@section('content')
<x-page-header :title="__('Account security')" :breadcrumbs="[__('Account security') => null]" />
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold d-flex justify-content-between">
                <span>{{ __('Two-factor authentication') }}</span>
                <span class="badge text-bg-{{ $user->hasTwoFactor() ? 'success' : 'secondary' }}">{{ $user->hasTwoFactor() ? __('On') : __('Off') }}</span>
            </div>
            <div class="card-body">
                @if($recoveryCodes)
                    <div class="alert alert-warning"><strong>{{ __('Recovery codes') }}</strong><br>{{ __('Keep these somewhere safe. Each code works once if you lose your phone. They will not be shown again.') }}
                        <pre class="mt-2 mb-0 fs-6" dir="ltr">{{ implode("\n", $recoveryCodes) }}</pre></div>
                @endif

                @if($user->hasTwoFactor())
                    <p>{{ __('Every sign-in asks for a code from your authenticator app.') }} <span class="text-body-secondary">{{ __(':n recovery code(s) left.', ['n' => $codesLeft]) }}</span></p>
                    <form method="POST" action="{{ route('account.security.codes') }}" class="row g-2 mb-3">@csrf
                        <div class="col-sm-7"><input type="password" name="password" class="form-control form-control-sm @error('password') is-invalid @enderror" placeholder="{{ __('Your password') }}" aria-label="{{ __('Your password') }}" required autocomplete="current-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-sm-5"><button class="btn btn-sm btn-outline-secondary w-100">{{ __('New recovery codes') }}</button></div>
                    </form>
                    @unless($user->requiresTwoFactor())
                    <form method="POST" action="{{ route('account.security.disable') }}" class="row g-2" onsubmit="return confirm('{{ __('Switch off two-factor authentication?') }}')">@csrf @method('DELETE')
                        <div class="col-sm-7"><input type="password" name="password" class="form-control form-control-sm" placeholder="{{ __('Your password') }}" aria-label="{{ __('Your password') }}" required autocomplete="current-password"></div>
                        <div class="col-sm-5"><button class="btn btn-sm btn-outline-danger w-100">{{ __('Switch off') }}</button></div>
                    </form>
                    @endunless
                @elseif($pendingSecret)
                    <ol class="ps-3">
                        <li class="mb-2">{{ __('Open your authenticator app (Google Authenticator, Microsoft Authenticator, Authy …) and add an account with this key:') }}
                            <div class="my-2"><code class="fs-5 user-select-all" dir="ltr">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</code></div>
                            <details class="small text-body-secondary"><summary>{{ __('Setup link') }}</summary><code class="user-select-all text-break" dir="ltr">{{ $uri }}</code></details></li>
                        <li>{{ __('Type the 6-digit code the app shows:') }}</li>
                    </ol>
                    <form method="POST" action="{{ route('account.security.confirm') }}" class="row g-2">@csrf
                        <div class="col-sm-5"><input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" class="form-control @error('code') is-invalid @enderror" placeholder="123456" aria-label="{{ __('Code') }}" required autofocus>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-auto"><button class="btn btn-brand">{{ __('Turn on') }}</button></div>
                    </form>
                    <form method="POST" action="{{ route('account.security.cancel') }}" class="mt-2">@csrf<button class="btn btn-link btn-sm p-0">{{ __('Cancel') }}</button></form>
                @else
                    <p>{{ __('Add a second step to your sign-in: a code from an app on your phone.') }}@if($user->requiresTwoFactor()) <strong>{{ __('Your role requires it.') }}</strong>@endif</p>
                    <form method="POST" action="{{ route('account.security.start') }}">@csrf<button class="btn btn-brand">{{ __('Set up') }}</button></form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
