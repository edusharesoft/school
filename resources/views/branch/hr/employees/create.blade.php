@extends('layouts.branch')
@section('title', __('New employee'))

@section('content')
<x-page-header :title="__('New employee')" :breadcrumbs="[__('Employees') => route('branch.hr.employees.index'), __('New') => null]" />

<form method="POST" action="{{ route('branch.hr.employees.store') }}" enctype="multipart/form-data" x-data="{ account: {{ old('create_account') ? 'true' : 'false' }} }">
    @csrf
    @include('branch.hr.employees.partials.fields', ['canSeeConfidential' => auth()->user()->can('employees.view-salary')])

    <div class="card mb-3">
        <div class="card-header bg-transparent d-flex align-items-center">
            <h2 class="h6 mb-0 me-auto"><i class="fa-solid fa-key me-1 text-primary"></i> {{ __('Login account') }}</h2>
            @if($roles->isNotEmpty() && auth()->user()->can('users.create'))
                <div class="form-check form-switch mb-0">
                    <input type="hidden" name="create_account" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="create_account" name="create_account" value="1" x-model="account">
                    <label class="form-check-label" for="create_account">{{ __('Create a login for this employee') }}</label>
                </div>
            @endif
        </div>
        @if($roles->isNotEmpty() && auth()->user()->can('users.create'))
            <div class="card-body row g-3" x-show="account" x-cloak>
                <x-form.input name="account_email" type="email" :label="__('Login e-mail')" col="col-md-4" autocomplete="off" />
                <x-form.input name="account_password" type="password" :label="__('Initial password')" col="col-md-4" autocomplete="new-password"
                    :help="__('At least 8 characters with letters and numbers. Share it privately.')" />
                <x-form.select name="account_role" :label="__('Role')" col="col-md-4" :options="$roles->all()" placeholder="—"
                    :help="__('Teachers get the teacher portal; other roles use the back-office.')" />
            </div>
        @else
            <div class="card-body small text-body-secondary">{{ __('You can add a login later from the employee profile (requires the user-management permission).') }}</div>
        @endif
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-brand" type="submit"><i class="bi bi-check2 me-1"></i>{{ __('Save employee') }}</button>
        <a href="{{ route('branch.hr.employees.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
