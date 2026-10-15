@extends('layouts.branch')
@section('title', $driver->exists ? __('Edit person') : __('Add person'))

@section('content')
<x-page-header :title="$driver->exists ? __('Edit person') : __('Add person')" :breadcrumbs="[__('Drivers & helpers') => route('branch.transport.drivers.index'), ($driver->exists ? __('Edit') : __('New')) => null]" />
<form method="POST" class="card" style="max-width: 900px" action="{{ $driver->exists ? route('branch.transport.drivers.update', $driver) : route('branch.transport.drivers.store') }}">
    @csrf @if($driver->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.select name="role" :label="__('Role')" :options="['driver' => __('Driver'), 'helper' => __('Helper')]" :value="$driver->role" required col="col-md-3" />
        <x-form.input name="name" :label="__('Name')" :value="$driver->name" required maxlength="120" col="col-md-5" />
        <x-form.input name="phone" :label="__('Phone')" :value="$driver->phone" required maxlength="30" col="col-md-4" />
        <x-form.input name="license_no" :label="__('Licence number')" :value="$driver->license_no" maxlength="50" col="col-md-4" :help="__('Required for drivers')" />
        <x-form.input name="license_expiry" type="date" :label="__('Licence expiry')" :value="$driver->license_expiry?->toDateString()" col="col-md-4" />
        <x-form.input name="nid_number" :label="__('NID number')" :value="$driver->nid_number" maxlength="30" col="col-md-4" />
        <x-form.input name="address" :label="__('Address')" :value="$driver->address" maxlength="255" col="col-12" />
        <x-form.input name="emergency_contact_name" :label="__('Emergency contact — name')" :value="$driver->emergency_contact_name" maxlength="120" col="col-md-6" />
        <x-form.input name="emergency_contact_phone" :label="__('Emergency contact — phone')" :value="$driver->emergency_contact_phone" maxlength="30" col="col-md-6" />
        <x-form.switch name="is_active" :label="__('Active')" :checked="$driver->exists ? $driver->is_active : true" />
    </div>
    <x-form.actions :cancel="route('branch.transport.drivers.index')" />
</form>
@endsection
