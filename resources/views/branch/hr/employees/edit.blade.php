@extends('layouts.branch')
@section('title', __('Edit employee'))

@section('content')
<x-page-header :title="__('Edit employee')" :breadcrumbs="[__('Employees') => route('branch.hr.employees.index'), $employee->name => route('branch.hr.employees.show', $employee), __('Edit') => null]" />

<form method="POST" action="{{ route('branch.hr.employees.update', $employee) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('branch.hr.employees.partials.fields')
    <div class="d-flex gap-2">
        <button class="btn btn-brand" type="submit"><i class="bi bi-check2 me-1"></i>{{ __('Save changes') }}</button>
        <a href="{{ route('branch.hr.employees.show', $employee) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
