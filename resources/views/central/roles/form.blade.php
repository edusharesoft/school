@extends('layouts.central')
@section('title', $role->exists ? __('Edit role') : __('New role'))

@section('content')
<x-page-header :title="$role->exists ? __('Edit role: :name', ['name' => $role->label ?? $role->name]) : __('New role')"
    :breadcrumbs="[__('Roles') => route('central.roles.index'), ($role->exists ? $role->name : __('New')) => null]" />

@php($checked = old('permissions', $granted))

<form method="POST" action="{{ $role->exists ? route('central.roles.update', $role) : route('central.roles.store') }}">
    @csrf
    @if($role->exists) @method('PUT') @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label" for="name">{{ __('Key') }} *</label>
                <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $role->name) }}" @disabled($role->is_system) required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label" for="label">{{ __('Label') }} *</label>
                <input id="label" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label', $role->label) }}" required>
                @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-2"><label class="form-label" for="label_bn">{{ __('Label (Bangla)') }}</label>
                <input id="label_bn" name="label_bn" class="form-control bn" value="{{ old('label_bn', $role->label_bn) }}"></div>
            <div class="col-md-2"><label class="form-label" for="scope">{{ __('Scope') }}</label>
                <select id="scope" name="scope" class="form-select" @disabled($role->is_system)>
                    <option value="branch" @selected(old('scope', $role->scope) === 'branch')>{{ __('Branch') }}</option>
                    <option value="central" @selected(old('scope', $role->scope) === 'central')>{{ __('Institution-wide') }}</option>
                </select></div>
            <div class="col-md-2"><label class="form-label" for="portal">{{ __('Portal') }}</label>
                <select id="portal" name="portal" class="form-select" @disabled($role->is_system)>
                    @foreach(['admin', 'teacher', 'student', 'parent'] as $p)<option value="{{ $p }}" @selected(old('portal', $role->portal) === $p)>{{ __(ucfirst($p)) }}</option>@endforeach
                </select></div>
        </div>
    </div>

    <div class="card perm-matrix" x-data>
        <div class="card-header bg-transparent d-flex align-items-center">
            <h2 class="h6 mb-0">{{ __('Permissions') }}</h2>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="$root.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = true)">{{ __('Select all') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="$root.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false)">{{ __('Clear') }}</button>
            </div>
        </div>
        <div class="card-body">
            @foreach($matrix as $module => $permissions)
                <div class="module" x-data>
                    <div class="d-flex align-items-center mb-1">
                        <strong class="text-capitalize">{{ str_replace('-', ' ', $module) }}</strong>
                        <button type="button" class="btn btn-link btn-sm ms-2 p-0" @click="$el.closest('.module').querySelectorAll('input').forEach(c => c.checked = !c.checked)">{{ __('toggle') }}</button>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($permissions as $permission)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" id="p-{{ $permission }}" @checked(in_array($permission, $checked, true))>
                                <label class="form-check-label small" for="p-{{ $permission }}">{{ \Illuminate\Support\Str::after($permission, '.') }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-brand" type="submit">{{ __('Save role') }}</button>
        <a href="{{ route('central.roles.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
