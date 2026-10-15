@extends('layouts.branch')
@section('title', $department->exists ? __('Edit department') : __('New department'))

@section('content')
<x-page-header :title="$department->exists ? __('Edit department') : __('New department')"
    :breadcrumbs="[__('Departments') => route('branch.departments.index'), ($department->exists ? $department->name : __('New')) => null]" />

<form method="POST" action="{{ $department->exists ? route('branch.departments.update', $department) : route('branch.departments.store') }}" class="card">
    @csrf
    @if($department->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-5"><label class="form-label" for="name">{{ __('Name') }} *</label>
            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $department->name) }}" required maxlength="191">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-5"><label class="form-label" for="name_bn">{{ __('Name (Bangla)') }}</label>
            <input id="name_bn" name="name_bn" class="form-control bn" value="{{ old('name_bn', $department->name_bn) }}" maxlength="191"></div>
        <div class="col-md-2"><label class="form-label" for="code">{{ __('Code') }} *</label>
            <input id="code" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror" value="{{ old('code', $department->code) }}" required maxlength="30">
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label class="form-label" for="description">{{ __('Description') }}</label>
            <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror" maxlength="2000">{{ old('description', $department->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12 form-check form-switch ms-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $department->is_active))>
            <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        <button class="btn btn-brand" type="submit">{{ __('Save') }}</button>
        <a href="{{ route('branch.departments.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
