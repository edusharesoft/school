@extends('layouts.branch')
@section('title', __('Branch settings'))

@section('content')
<x-page-header :title="__('Branch settings')" :breadcrumbs="[$branch->name => route('home.redirect'), __('Settings') => null]" />

<form method="POST" action="{{ route('branch.settings.update') }}" enctype="multipart/form-data" class="row g-4">
    @csrf @method('PUT')
    <div class="col-xl-7">
        <div class="panel h-100">
            <div class="panel-head"><span><i class="fa-solid fa-school text-primary me-1"></i> {{ __('Branch profile') }}</span>
                <code class="small">{{ $branch->host() }}</code></div>
            <div class="panel-body row g-3">
                <div class="col-md-6"><label class="form-label small fw-semibold">{{ __('Branch name') }}</label>
                    <input class="form-control" value="{{ $branch->name }}" disabled>
                    <div class="form-text">{{ __('Name, code and address (subdomain) are changed by the central administration.') }}</div></div>
                <x-form.input name="name_bn" :label="__('Branch name (Bangla)')" :value="$branch->name_bn" class="bn" />
                <x-form.input name="principal_name" :label="__('Principal')" :value="$branch->principal_name" />
                <x-form.input name="phone" :label="__('Phone')" :value="$branch->phone" />
                <x-form.input name="email" type="email" :label="__('E-mail')" :value="$branch->email" />
                <x-form.select name="locale" :label="__('Default language')" :value="$branch->locale" :options="['en' => 'English', 'bn' => 'বাংলা']" />
                <x-form.input name="address" :label="__('Address')" :value="$branch->address" col="col-12" />
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="logo">{{ __('Branch logo') }}</label>
                    @if ($branch->logo_path)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="{{ $branch->logoUrl() }}" alt="" class="rounded border" style="height:48px;max-width:140px;object-fit:contain">
                            <div class="form-check small"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rm_logo">
                                <label class="form-check-label text-danger" for="rm_logo">{{ __('Remove') }}</label></div>
                        </div>
                    @endif
                    <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm @error('logo') is-invalid @enderror">
                    @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3"><label class="form-label small fw-semibold" for="primary_color">{{ __('Brand colour') }}</label>
                    <input type="color" id="primary_color" name="primary_color" class="form-control form-control-color w-100" value="{{ old('primary_color', $branch->brandColor()) }}"></div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="panel h-100">
            <div class="panel-head"><span><i class="fa-solid fa-right-to-bracket text-primary me-1"></i> {{ __('This branch\'s login page') }}</span></div>
            <div class="panel-body row g-3">
                @include('partials.branding-image', ['key' => 'auth_login_bg', 'label' => __('Login page background'), 'path' => $values['auth_login_bg'],
                    'hint' => __('Empty = institution / platform default.'), 'col' => 'col-12'])
                <div class="col-12"><label class="form-label small fw-semibold" for="auth_login_video"><i class="fa-brands fa-youtube text-danger"></i> {{ __('Login page YouTube video') }}</label>
                    <input id="auth_login_video" name="auth_login_video" value="{{ old('auth_login_video', $values['auth_login_video']) }}" class="form-control @error('auth_login_video') is-invalid @enderror" placeholder="https://www.youtube.com/watch?v=...">
                    @error('auth_login_video')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="auth_show_video" value="0">
                        <input class="form-check-input" type="checkbox" name="auth_show_video" value="1" id="asv" @checked(old('auth_show_video', $values['auth_show_video'] ?? true))>
                        <label class="form-check-label small" for="asv">{{ __('Show the video on login / registration pages') }}</label>
                    </div>
                </div>
                <div class="col-12 small"><a href="{{ route('login') }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Preview login page') }}</a>
                    <span class="text-body-secondary">({{ __('opens in a private window to see it signed out') }})</span></div>
            </div>
        </div>
    </div>
    @can('settings.update')
        <div class="col-12 text-end"><button class="btn btn-primary px-4" type="submit">{{ __('Save') }}</button></div>
    @endcan
</form>
@endsection
