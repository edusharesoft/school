@extends('layouts.branch')
@section('title', $type->exists ? __('Edit leave type') : __('New leave type'))

@section('content')
<x-page-header :title="$type->exists ? __('Edit leave type') : __('New leave type')"
    :breadcrumbs="[__('Leave types') => route('branch.hr.leave-types.index'), ($type->exists ? $type->name : __('New')) => null]" />

<form method="POST" action="{{ $type->exists ? route('branch.hr.leave-types.update', $type) : route('branch.hr.leave-types.store') }}" class="card">
    @csrf
    @if($type->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$type->name" required col="col-md-5" maxlength="80" />
        <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$type->name_bn" col="col-md-5" class="bn" maxlength="80" />
        <x-form.input name="code" :label="__('Code')" :value="$type->code" required col="col-md-2" class="text-uppercase" maxlength="20" />
        <x-form.input name="days_per_year" type="number" :label="__('Days per year')" :value="$type->days_per_year" required col="col-md-3" min="0" max="366"
            :help="__('0 = unlimited (e.g. leave without pay).')" />
        <x-form.switch name="is_paid" :label="__('Paid leave')" :checked="$type->is_paid" col="col-md-3 pt-md-4" />
        <x-form.switch name="is_active" :label="__('Active')" :checked="$type->is_active" col="col-md-3 pt-md-4" />
    </div>
    <x-form.actions :cancel="route('branch.hr.leave-types.index')" />
</form>
@endsection
