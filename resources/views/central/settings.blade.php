@extends('layouts.central')
@section('title', __('Settings'))

@section('content')
<x-page-header :title="__('Settings')" :breadcrumbs="[__('System') => null, __('Settings') => null]" />

<div class="row g-4">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('central.settings.update') }}" enctype="multipart/form-data" class="panel">
            @csrf @method('PUT')

            <div class="panel-head">
                <span><i class="fa-solid fa-sliders text-primary me-1"></i> {{ $platform ? __('Platform (main website & defaults)') : __('Institution: :name', ['name' => $institution?->name]) }}</span>
                <span class="badge text-bg-light border fw-normal">{{ $platform ? __('Applies to the main domain and every institution without its own value') : __('Applies to all branches of this institution') }}</span>
            </div>
            <div class="panel-body row g-3">
                <div class="col-md-6"><label class="form-label small fw-semibold" for="site_name">{{ __('Site name') }}</label>
                    <input id="site_name" name="site_name" value="{{ old('site_name', $values['site_name']) }}" class="form-control" placeholder="{{ config('app.name') }}"></div>
                <div class="col-md-6"><label class="form-label small fw-semibold" for="footer_text">{{ __('Footer text') }}</label>
                    <input id="footer_text" name="footer_text" value="{{ old('footer_text', $values['footer_text']) }}" class="form-control" placeholder="{{ App\Support\AuthTheme::DEFAULT_FOOTER }}">
                    <div class="form-text">{{ __('Shown under the login card and in the panel footer. "Name : Value" — the part after ":" is shown in bold.') }}</div></div>
                @if ($platform)
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="allow_registration" value="0">
                            <input class="form-check-input" type="checkbox" name="allow_registration" value="1" id="reg" @checked(old('allow_registration', $values['allow_registration']))>
                            <label class="form-check-label small" for="reg">{{ __('Allow new institutions to register themselves (shows "Register your institution" on the main login page)') }}</label>
                        </div>
                    </div>
                @endif
            </div>

            <div class="panel-head border-top"><span><i class="fa-solid fa-image text-primary me-1"></i> {{ __('Logo & login / registration page') }}</span></div>
            <div class="panel-body row g-3">
                @if ($platform)
                    @include('partials.branding-image', ['key' => 'site_logo', 'label' => __('Platform logo'), 'path' => $values['site_logo'], 'fit' => 'contain',
                        'hint' => __('PNG / WebP, transparent, about 400×120 — main website, login pages and panels without their own logo.')])
                @else
                    <div class="col-md-4 small text-body-secondary">{{ __('The institution logo is changed under Institutions → Edit.') }}</div>
                @endif
                @include('partials.branding-image', ['key' => 'auth_login_bg', 'label' => __('Login page background'), 'path' => $values['auth_login_bg'],
                    'hint' => __('Wide photo, 1920×1080, max 4 MB.')])
                @include('partials.branding-image', ['key' => 'auth_register_bg', 'label' => __('Registration page background'), 'path' => $values['auth_register_bg'],
                    'hint' => __('Empty = same as login.')])

                <div class="col-md-6"><label class="form-label small fw-semibold" for="auth_login_video"><i class="fa-brands fa-youtube text-danger"></i> {{ __('Login page YouTube video') }}</label>
                    <input id="auth_login_video" name="auth_login_video" value="{{ old('auth_login_video', $values['auth_login_video']) }}" class="form-control @error('auth_login_video') is-invalid @enderror" placeholder="https://www.youtube.com/watch?v=...">
                    @error('auth_login_video')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if ($id = App\Support\AuthTheme::youtubeId($values['auth_login_video']))<div class="form-text text-success">✓ {{ __('Video ID') }}: {{ $id }}</div>@endif</div>
                <div class="col-md-6"><label class="form-label small fw-semibold" for="auth_register_video"><i class="fa-brands fa-youtube text-danger"></i> {{ __('Registration page YouTube video') }}</label>
                    <input id="auth_register_video" name="auth_register_video" value="{{ old('auth_register_video', $values['auth_register_video']) }}" class="form-control @error('auth_register_video') is-invalid @enderror" placeholder="{{ __('Empty = same as login.') }}">
                    @error('auth_register_video')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if ($id = App\Support\AuthTheme::youtubeId($values['auth_register_video']))<div class="form-text text-success">✓ {{ __('Video ID') }}: {{ $id }}</div>@endif</div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="auth_dim">{{ __('Background darkness') }}: <span>{{ round((float) old('auth_dim', $values['auth_dim'] ?? 0.45) * 100) }}%</span></label>
                    <input type="range" class="form-range" min="0" max="0.85" step="0.05" id="auth_dim" name="auth_dim"
                           value="{{ old('auth_dim', $values['auth_dim'] ?? 0.45) }}" oninput="this.previousElementSibling.querySelector('span').textContent = Math.round(this.value * 100) + '%'">
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-3 align-items-center">
                    <div class="form-check form-switch">
                        <input type="hidden" name="auth_show_video" value="0">
                        <input class="form-check-input" type="checkbox" name="auth_show_video" value="1" id="asv" @checked(old('auth_show_video', $values['auth_show_video'] ?? true))>
                        <label class="form-check-label small" for="asv">{{ __('Show the video on login / registration pages') }}</label>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-3 small">
                    <a href="{{ route('login') }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Preview login page') }}</a>
                    @if ($platform && App\Support\AuthTheme::registrationOpen())
                        <a href="{{ route('register') }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Preview registration page') }}</a>
                    @endif
                </div>
            </div>
            <div class="panel-body border-top text-end"><button class="btn btn-primary px-4" type="submit">{{ __('Save') }}</button></div>
        </form>
    </div>

    <div class="col-xl-4">
        <div class="panel mb-3">
            <div class="panel-head"><span><i class="fa-solid fa-circle-info text-primary me-1"></i> {{ __('How the look is chosen') }}</span></div>
            <div class="panel-body small">
                <ol class="mb-0 ps-3">
                    <li>{{ __('Branch settings (each branch can set its own logo, login photo and video)') }}</li>
                    <li>{{ __('Institution settings') }}</li>
                    <li>{{ __('Platform settings (Super Admin)') }}</li>
                </ol>
                <p class="mt-2 mb-0 text-body-secondary">{{ __('The first level that has a value wins. The main-domain login page always uses the platform settings.') }}</p>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><span><i class="fa-solid fa-plug text-primary me-1"></i> {{ __('Gateways & integrations') }}</span></div>
            <div class="panel-body small text-body-secondary">{{ __('SMS, e-mail, WhatsApp and payment gateway (SSLCOMMERZ, bKash, Nagad …) settings are added here in Part 5.') }}</div>
        </div>
    </div>
</div>
@endsection
