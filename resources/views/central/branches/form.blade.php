@extends('layouts.central')
@section('title', $branch->exists ? __('Edit branch') : __('New branch'))

@section('content')
<x-page-header :title="$branch->exists ? __('Edit branch') : __('New branch')"
    :breadcrumbs="[__('Branches') => route('central.branches.index'), ($branch->exists ? $branch->name : __('New')) => null]" />

<form method="POST" enctype="multipart/form-data" action="{{ $branch->exists ? route('central.branches.update', $branch) : route('central.branches.store') }}" class="card"
      x-data="{ sub: @js(old('subdomain', $branch->subdomain ?? '')) }">
    @csrf
    @if($branch->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        @if(! $branch->exists && $institutions->count() > 1)
            <div class="col-md-6"><label class="form-label" for="institution_id">{{ __('Institution') }} *</label>
                <select id="institution_id" name="institution_id" class="form-select">
                    @foreach($institutions as $id => $name)<option value="{{ $id }}" @selected((int) old('institution_id') === $id)>{{ $name }}</option>@endforeach
                </select></div>
        @endif
        <div class="col-md-6"><label class="form-label" for="name">{{ __('Branch name') }} *</label>
            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $branch->name) }}" required maxlength="191">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="name_bn">{{ __('Branch name (Bangla)') }}</label>
            <input id="name_bn" name="name_bn" class="form-control bn" value="{{ old('name_bn', $branch->name_bn) }}" maxlength="191"></div>
        <div class="col-md-3"><label class="form-label" for="code">{{ __('Branch code') }} *</label>
            <input id="code" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror" value="{{ old('code', $branch->code) }}" required maxlength="30">
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-5"><label class="form-label" for="subdomain">{{ __('Subdomain') }} *</label>
            <div class="input-group">
                <input id="subdomain" name="subdomain" x-model="sub" class="form-control @error('subdomain') is-invalid @enderror" required maxlength="63" pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?">
                <span class="input-group-text">.{{ config('tenancy.base_domain') }}</span>
                @error('subdomain')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-text">{{ __('Lower-case letters, digits and hyphens.') }} <span x-show="sub" x-text="sub + '.{{ config('tenancy.base_domain') }}'"></span>
                @if($branch->exists)<span class="text-warning">{{ __('Changing it changes the branch address for everyone.') }}</span>@endif</div>
        </div>
        @unless($branch->exists)
            <div class="col-md-4"><label class="form-label" for="custom_domain">{{ __('Custom domain (optional)') }}</label>
                <input id="custom_domain" name="custom_domain" class="form-control @error('custom_domain') is-invalid @enderror" value="{{ old('custom_domain') }}" placeholder="dhanmondi.example.edu.bd">
                @error('custom_domain')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        @endunless
        <div class="col-md-4"><label class="form-label" for="type">{{ __('Type') }}</label>
            <select id="type" name="type" class="form-select">
                @foreach(['school' => __('School'), 'college' => __('College'), 'school_college' => __('School & College')] as $v => $l)
                    <option value="{{ $v }}" @selected(old('type', $branch->type) === $v)>{{ $l }}</option>
                @endforeach
            </select></div>
        <div class="col-md-4"><label class="form-label" for="email">{{ __('E-mail') }}</label>
            <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $branch->email) }}">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label" for="phone">{{ __('Phone') }}</label>
            <input id="phone" name="phone" class="form-control" value="{{ old('phone', $branch->phone) }}"></div>
        <div class="col-md-8"><label class="form-label" for="address">{{ __('Address') }}</label>
            <input id="address" name="address" class="form-control" value="{{ old('address', $branch->address) }}"></div>
        <div class="col-md-4"><label class="form-label" for="principal_name">{{ __('Principal') }}</label>
            <input id="principal_name" name="principal_name" class="form-control" value="{{ old('principal_name', $branch->principal_name) }}"></div>
        <div class="col-md-3"><label class="form-label" for="timezone">{{ __('Time zone') }} *</label>
            <input id="timezone" name="timezone" class="form-control @error('timezone') is-invalid @enderror" value="{{ old('timezone', $branch->timezone) }}" required>
            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-2"><label class="form-label" for="currency">{{ __('Currency') }} *</label>
            <input id="currency" name="currency" class="form-control" value="{{ old('currency', $branch->currency) }}" maxlength="3" required></div>
        <div class="col-md-2"><label class="form-label" for="locale">{{ __('Default language') }}</label>
            <select id="locale" name="locale" class="form-select">
                @foreach(['en' => 'English', 'bn' => 'বাংলা'] as $v => $l)<option value="{{ $v }}" @selected(old('locale', $branch->locale) === $v)>{{ $l }}</option>@endforeach
            </select></div>
        <div class="col-md-2"><label class="form-label" for="status">{{ __('Status') }}</label>
            <select id="status" name="status" class="form-select">
                @foreach(['active', 'inactive'] as $s)<option value="{{ $s }}" @selected(old('status', $branch->status?->value ?? 'active') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach
            </select></div>
        <div class="col-md-3"><label class="form-label" for="established_on">{{ __('Established on') }}</label>
            <input id="established_on" type="date" name="established_on" class="form-control" value="{{ old('established_on', $branch->established_on?->toDateString()) }}"></div>
        <div class="col-md-2"><label class="form-label" for="primary_color">{{ __('Brand colour') }}</label>
            <input id="primary_color" type="color" name="primary_color" class="form-control form-control-color w-100" value="{{ old('primary_color', $branch->primary_color ?? '#1f6f5c') }}"></div>
        <div class="col-md-2"><label class="form-label" for="sort_order">{{ __('Sort order') }}</label>
            <input id="sort_order" type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $branch->sort_order ?? 0) }}"></div>
        <div class="col-md-5"><label class="form-label" for="logo">{{ __('Logo') }} <span class="text-body-secondary small">(≤1 MB)</span></label>
            <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control @error('logo') is-invalid @enderror">
            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><hr class="my-1"><div class="small text-body-secondary">{{ __('Public website') }}</div></div>
        <div class="col-md-5"><label class="form-label" for="cover">{{ __('Campus photo') }} <span class="text-body-secondary small">(≤4 MB, {{ __('shown on the home page') }})</span></label>
            <input id="cover" type="file" name="cover" accept="image/png,image/jpeg,image/webp" class="form-control @error('cover') is-invalid @enderror">
            @error('cover')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($branch->coverUrl())<img src="{{ $branch->coverUrl() }}" alt="" class="mt-2 rounded border" style="height:64px">@endif</div>
        <div class="col-md-3"><label class="form-label" for="latitude">{{ __('Latitude') }}</label>
            <input id="latitude" name="latitude" inputmode="decimal" class="form-control @error('latitude') is-invalid @enderror" value="{{ old('latitude', $branch->latitude) }}" placeholder="23.7937">
            @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="longitude">{{ __('Longitude') }}</label>
            <input id="longitude" name="longitude" inputmode="decimal" class="form-control @error('longitude') is-invalid @enderror" value="{{ old('longitude', $branch->longitude) }}" placeholder="90.4043">
            @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Used for the "All Branch Locations" map.') }}</div></div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        <button class="btn btn-brand" type="submit">{{ __('Save branch') }}</button>
        <a href="{{ $branch->exists ? route('central.branches.show', $branch) : route('central.branches.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
