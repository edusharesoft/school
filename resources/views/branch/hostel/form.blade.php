@extends('layouts.branch')
@section('title', $hostel->exists ? __('Edit hostel') : __('Add hostel'))

@section('content')
<x-page-header :title="$hostel->exists ? __('Edit hostel') : __('Add hostel')" :breadcrumbs="[__('Hostel') => route('branch.hostel.index'), ($hostel->exists ? __('Edit') : __('New')) => null]" />
<form method="POST" class="card" style="max-width: 800px" action="{{ $hostel->exists ? route('branch.hostel.buildings.update', $hostel) : route('branch.hostel.buildings.store') }}">
    @csrf @if($hostel->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$hostel->name" required maxlength="120" col="col-md-8" />
        <x-form.select name="type" :label="__('Type')" :options="collect(\App\Models\Hostel\Hostel::TYPES)->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])->all()" :value="$hostel->type" required col="col-md-4" />
        <x-form.select name="warden_id" :label="__('Warden')" :options="$wardens" :value="$hostel->warden_id" :placeholder="__('— None —')" col="col-md-6" />
        <x-form.input name="phone" :label="__('Phone')" :value="$hostel->phone" maxlength="30" col="col-md-6" />
        <x-form.input name="address" :label="__('Address')" :value="$hostel->address" maxlength="255" col="col-12" />
        <x-form.switch name="is_active" :label="__('Active')" :checked="$hostel->exists ? $hostel->is_active : true" />
    </div>
    <x-form.actions :cancel="route('branch.hostel.index')" />
</form>
@endsection
