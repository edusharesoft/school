@extends('layouts.central')
@section('title', $plan->exists ? __('Edit plan') : __('New plan'))

@section('content')
<x-page-header :title="$plan->exists ? __('Edit plan') : __('New plan')" :breadcrumbs="[__('Plans') => route('central.plans.index'), ($plan->exists ? $plan->name : __('New')) => null]" />
<form method="POST" action="{{ $plan->exists ? route('central.plans.update', $plan) : route('central.plans.store') }}" class="card">
    @csrf
    @if($plan->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        @php($f = fn ($n) => old($n, $plan->$n))
        <div class="col-md-3"><label class="form-label" for="code">{{ __('Code') }} *</label><input id="code" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ $f('code') }}" required maxlength="40">@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-5"><label class="form-label" for="name">{{ __('Name') }} *</label><input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ $f('name') }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label" for="name_bn">{{ __('Name (Bangla)') }}</label><input id="name_bn" name="name_bn" class="form-control bn" value="{{ $f('name_bn') }}"></div>
        <div class="col-12"><label class="form-label" for="description">{{ __('Description') }}</label><input id="description" name="description" class="form-control" value="{{ $f('description') }}" maxlength="1000"></div>
        <div class="col-md-3"><label class="form-label" for="price_monthly">{{ __('Monthly price') }} *</label><input id="price_monthly" type="number" step="0.01" min="0" name="price_monthly" class="form-control @error('price_monthly') is-invalid @enderror" value="{{ $f('price_monthly') }}" required>@error('price_monthly')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="price_yearly">{{ __('Yearly price') }} *</label><input id="price_yearly" type="number" step="0.01" min="0" name="price_yearly" class="form-control @error('price_yearly') is-invalid @enderror" value="{{ $f('price_yearly') }}" required>@error('price_yearly')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="max_branches">{{ __('Branch limit') }}</label><input id="max_branches" type="number" min="1" name="max_branches" class="form-control @error('max_branches') is-invalid @enderror" value="{{ $f('max_branches') }}" placeholder="{{ __('Unlimited') }}">@error('max_branches')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="max_students">{{ __('Active student limit') }}</label><input id="max_students" type="number" min="1" name="max_students" class="form-control @error('max_students') is-invalid @enderror" value="{{ $f('max_students') }}" placeholder="{{ __('Unlimited') }}">@error('max_students')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="trial_days">{{ __('Trial days') }}</label><input id="trial_days" type="number" min="0" name="trial_days" class="form-control" value="{{ $f('trial_days') }}"><div class="form-text">{{ __('New registrations start on the first active plan with a trial.') }}</div></div>
        <div class="col-md-3"><label class="form-label" for="grace_days">{{ __('Grace days after expiry') }}</label><input id="grace_days" type="number" min="0" name="grace_days" class="form-control" value="{{ $f('grace_days') }}"></div>
        <div class="col-md-3"><label class="form-label" for="sort_order">{{ __('Order') }}</label><input id="sort_order" type="number" min="0" name="sort_order" class="form-control" value="{{ $f('sort_order') }}"></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $plan->is_active))><label class="form-check-label" for="is_active">{{ __('Active') }}</label></div></div>
    </div>
    <div class="card-footer text-end"><a href="{{ route('central.plans.index') }}" class="btn btn-light">{{ __('Cancel') }}</a> <button class="btn btn-brand">{{ __('Save') }}</button></div>
</form>
@endsection
