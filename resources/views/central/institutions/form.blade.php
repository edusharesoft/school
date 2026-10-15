@extends('layouts.central')
@section('title', $institution->exists ? __('Edit institution') : __('New institution'))

@section('content')
<x-page-header :title="$institution->exists ? __('Edit institution') : __('New institution')"
    :breadcrumbs="[__('Institutions') => route('central.institutions.index'), ($institution->exists ? $institution->name : __('New')) => null]" />

<form method="POST" enctype="multipart/form-data" action="{{ $institution->exists ? route('central.institutions.update', $institution) : route('central.institutions.store') }}" class="card">
    @csrf
    @if($institution->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label" for="name">{{ __('Name') }} *</label>
            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $institution->name) }}" required maxlength="191">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="name_bn">{{ __('Name (Bangla)') }}</label>
            <input id="name_bn" name="name_bn" class="form-control bn" value="{{ old('name_bn', $institution->name_bn) }}" maxlength="191"></div>
        <div class="col-md-3"><label class="form-label" for="slug">{{ __('Slug') }} *</label>
            <input id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $institution->slug) }}" required>
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="code">{{ __('Code') }} *</label>
            <input id="code" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $institution->code) }}" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="email">{{ __('E-mail') }}</label>
            <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $institution->email) }}">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="phone">{{ __('Phone') }}</label>
            <input id="phone" name="phone" class="form-control" value="{{ old('phone', $institution->phone) }}"></div>
        <div class="col-md-6"><label class="form-label" for="website">{{ __('Website') }}</label>
            <input id="website" type="url" name="website" class="form-control @error('website') is-invalid @enderror" value="{{ old('website', $institution->website) }}">
            @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="address">{{ __('Address') }}</label>
            <input id="address" name="address" class="form-control" value="{{ old('address', $institution->address) }}"></div>
        <div class="col-md-3"><label class="form-label" for="timezone">{{ __('Time zone') }} *</label>
            <input id="timezone" name="timezone" class="form-control @error('timezone') is-invalid @enderror" value="{{ old('timezone', $institution->timezone) }}" required>
            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-2"><label class="form-label" for="currency">{{ __('Currency') }} *</label>
            <input id="currency" name="currency" class="form-control" value="{{ old('currency', $institution->currency) }}" maxlength="3" required></div>
        <div class="col-md-2"><label class="form-label" for="locale">{{ __('Default language') }}</label>
            <select id="locale" name="locale" class="form-select">
                @foreach(['en' => 'English', 'bn' => 'বাংলা'] as $v => $l)<option value="{{ $v }}" @selected(old('locale', $institution->locale) === $v)>{{ $l }}</option>@endforeach
            </select></div>
        <div class="col-md-2"><label class="form-label" for="primary_color">{{ __('Brand colour') }}</label>
            <input id="primary_color" type="color" name="primary_color" class="form-control form-control-color w-100" value="{{ old('primary_color', $institution->primary_color ?? '#1f6f5c') }}"></div>
        <div class="col-md-3"><label class="form-label" for="status">{{ __('Status') }}</label>
            <select id="status" name="status" class="form-select" @disabled(! auth()->user()->isPlatformOwner() && $institution->exists)>
                @foreach(['active', 'inactive', 'suspended'] as $s)<option value="{{ $s }}" @selected(old('status', $institution->status?->value ?? 'active') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach
            </select>
            @if(! auth()->user()->isPlatformOwner() && $institution->exists)<input type="hidden" name="status" value="{{ $institution->status->value }}">@endif
        </div>
        <div class="col-md-6"><label class="form-label" for="logo">{{ __('Logo') }} <span class="text-body-secondary small">(PNG/JPG/WebP, ≤1 MB)</span></label>
            <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control @error('logo') is-invalid @enderror">
            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        <button class="btn btn-brand" type="submit">{{ __('Save') }}</button>
        <a href="{{ route('central.institutions.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
