@extends('layouts.central')
@section('title', $user->exists ? __('Edit user') : __('New user'))

@section('content')
<x-page-header :title="$user->exists ? __('Edit user') : __('New user')"
    :breadcrumbs="[__('Users') => route('central.users.index'), ($user->exists ? $user->name : __('New')) => null]" />

@php
    $selectedRoles = old('roles', $user->exists ? $user->roles->pluck('name')->all() : []);
    $selectedBranches = array_map('intval', old('branches', $user->exists ? $user->branches->pluck('id')->all() : []));
@endphp

<form method="POST" action="{{ $user->exists ? route('central.users.update', $user) : route('central.users.store') }}">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Account') }}</h2></div>
                <div class="card-body row g-3">
                    @if($institutions->isNotEmpty() && ! $user->exists)
                        <div class="col-12"><label class="form-label" for="institution_id">{{ __('Institution') }}</label>
                            <select id="institution_id" name="institution_id" class="form-select">
                                @foreach($institutions as $id => $name)<option value="{{ $id }}" @selected((int) old('institution_id') === $id)>{{ $name }}</option>@endforeach
                            </select></div>
                    @endif
                    <div class="col-md-6"><label class="form-label" for="name">{{ __('Full name') }} *</label>
                        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="name_bn">{{ __('Full name (Bangla)') }}</label>
                        <input id="name_bn" name="name_bn" class="form-control bn" value="{{ old('name_bn', $user->name_bn) }}"></div>
                    <div class="col-md-6"><label class="form-label" for="email">{{ __('E-mail') }} *</label>
                        <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required autocomplete="off">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-3"><label class="form-label" for="phone">{{ __('Mobile') }}</label>
                        <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}" placeholder="+8801XXXXXXXXX">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-3"><label class="form-label" for="username">{{ __('Username') }}</label>
                        <input id="username" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $user->username) }}">
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-4"><label class="form-label" for="user_type">{{ __('Account type') }}</label>
                        <select id="user_type" name="user_type" class="form-select">
                            @foreach($types as $v => $l)<option value="{{ $v }}" @selected(old('user_type', $user->user_type?->value) === $v)>{{ $l }}</option>@endforeach
                        </select>
                        <div class="form-text">{{ __('Decides the portal: staff → admin, teacher, student, parent.') }}</div></div>
                    <div class="col-md-4"><label class="form-label" for="status">{{ __('Status') }}</label>
                        <select id="status" name="status" class="form-select">
                            @foreach(['active', 'inactive', 'suspended'] as $s)<option value="{{ $s }}" @selected(old('status', $user->status?->value) === $s)>{{ __(ucfirst($s)) }}</option>@endforeach
                        </select></div>
                    <div class="col-md-4"><label class="form-label" for="locale">{{ __('Language') }}</label>
                        <select id="locale" name="locale" class="form-select"><option value="">{{ __('Branch default') }}</option>
                            @foreach(['en' => 'English', 'bn' => 'বাংলা'] as $v => $l)<option value="{{ $v }}" @selected(old('locale', $user->locale) === $v)>{{ $l }}</option>@endforeach
                        </select></div>
                    <div class="col-md-6"><label class="form-label" for="password">{{ $user->exists ? __('New password (leave blank to keep)') : __('Password') . ' *' }}</label>
                        <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @required(! $user->exists)>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Roles') }} *</h2></div>
                <div class="card-body" style="max-height: 320px; overflow:auto">
                    @error('roles')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    @foreach($roles->groupBy('scope') as $scope => $group)
                        <div class="small text-uppercase text-body-secondary mt-1 mb-1">{{ $scope === 'central' ? __('Institution-wide') : __('Branch level') }}</div>
                        @foreach($group as $role)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" id="role-{{ $role->id }}" @checked(in_array($role->name, $selectedRoles, true))>
                                <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->label ?? $role->name }} <span class="bn text-body-secondary small">{{ $role->label_bn }}</span></label>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
            <div class="card">
                <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Branch access') }}</h2></div>
                <div class="card-body" style="max-height: 320px; overflow:auto">
                    <div class="form-text mb-2">{{ __('Institution-wide roles already reach every branch. For branch roles, tick the branches this person works in.') }}</div>
                    @error('branches')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    @foreach($branches as $b)
                        <div class="d-flex align-items-center">
                            <div class="form-check flex-grow-1">
                                <input class="form-check-input" type="checkbox" name="branches[]" value="{{ $b->id }}" id="branch-{{ $b->id }}" @checked(in_array($b->id, $selectedBranches, true))>
                                <label class="form-check-label" for="branch-{{ $b->id }}">{{ $b->name }}</label>
                            </div>
                            <div class="form-check form-check-inline me-0" data-bs-toggle="tooltip" title="{{ __('Default branch') }}">
                                <input class="form-check-input" type="radio" name="default_branch_id" value="{{ $b->id }}" aria-label="{{ __('Default branch') }}" @checked((int) old('default_branch_id', $user->default_branch_id) === $b->id)>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-brand" type="submit">{{ __('Save user') }}</button>
        <a href="{{ route('central.users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
