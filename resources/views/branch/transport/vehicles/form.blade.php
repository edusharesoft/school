@extends('layouts.branch')
@section('title', $vehicle->exists ? __('Edit vehicle') : __('Add vehicle'))

@section('content')
<x-page-header :title="$vehicle->exists ? __('Edit vehicle') : __('Add vehicle')" :breadcrumbs="[__('Vehicles') => route('branch.transport.vehicles.index'), ($vehicle->exists ? __('Edit') : __('New')) => null]" />
<form method="POST" class="card" style="max-width: 900px" action="{{ $vehicle->exists ? route('branch.transport.vehicles.update', $vehicle) : route('branch.transport.vehicles.store') }}">
    @csrf @if($vehicle->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="registration_no" :label="__('Registration number')" :value="$vehicle->registration_no" required maxlength="40" col="col-md-4" />
        <x-form.input name="name" :label="__('Name / model')" :value="$vehicle->name" maxlength="100" col="col-md-4" />
        <x-form.select name="status" :label="__('Status')" :options="collect(\App\Models\Transport\Vehicle::STATUSES)->mapWithKeys(fn ($s) => [$s => __(ucfirst($s))])->all()" :value="$vehicle->status" required col="col-md-4" />
        <x-form.select name="type" :label="__('Type')" :options="collect(\App\Models\Transport\Vehicle::TYPES)->mapWithKeys(fn ($s) => [$s => __(ucfirst($s))])->all()" :value="$vehicle->type" required col="col-md-4" />
        <x-form.input name="capacity" type="number" min="1" :label="__('Seats for students')" :value="$vehicle->capacity" required col="col-md-4" />
        <x-form.select name="fuel_type" :label="__('Fuel')" :options="collect(\App\Models\Transport\Vehicle::FUELS)->mapWithKeys(fn ($s) => [$s => __(ucfirst($s))])->all()" :value="$vehicle->fuel_type" required col="col-md-4" />
        <div class="col-12 fw-semibold">{{ __('Paper expiry dates') }}</div>
        <x-form.input name="insurance_expiry" type="date" :label="__('Insurance')" :value="$vehicle->insurance_expiry?->toDateString()" col="col-md-3" />
        <x-form.input name="fitness_expiry" type="date" :label="__('Fitness certificate')" :value="$vehicle->fitness_expiry?->toDateString()" col="col-md-3" />
        <x-form.input name="tax_token_expiry" type="date" :label="__('Tax token')" :value="$vehicle->tax_token_expiry?->toDateString()" col="col-md-3" />
        <x-form.input name="route_permit_expiry" type="date" :label="__('Route permit')" :value="$vehicle->route_permit_expiry?->toDateString()" col="col-md-3" />
        <x-form.input name="tracking_url" type="url" :label="__('Live-tracking link (optional)')" :value="$vehicle->tracking_url" col="col-12" :help="__('The tracking page of your GPS provider (https). Guardians of riding students get a link to it. The platform itself does not track vehicles.')" />
        <x-form.input name="note" :label="__('Note')" :value="$vehicle->note" maxlength="255" col="col-12" />
    </div>
    <x-form.actions :cancel="route('branch.transport.vehicles.index')" />
</form>
@endsection
