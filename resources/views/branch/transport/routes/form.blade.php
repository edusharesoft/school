@extends('layouts.branch')
@section('title', $route->exists ? __('Edit route') : __('Add route'))

@section('content')
<x-page-header :title="$route->exists ? __('Edit route') : __('Add route')" :breadcrumbs="[__('Routes') => route('branch.transport.routes.index'), ($route->exists ? __('Edit') : __('New')) => null]" />
<form method="POST" class="card" style="max-width: 900px" action="{{ $route->exists ? route('branch.transport.routes.update', $route) : route('branch.transport.routes.store') }}">
    @csrf @if($route->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="code" :label="__('Code')" :value="$route->code" required maxlength="20" col="col-md-3" />
        <x-form.input name="name" :label="__('Route name')" :value="$route->name" required maxlength="120" col="col-md-9" />
        <x-form.select name="vehicle_id" :label="__('Vehicle')" :options="$vehicles" :value="$route->vehicle_id" :placeholder="__('— None —')" col="col-md-4" />
        <x-form.select name="driver_id" :label="__('Driver')" :options="$drivers" :value="$route->driver_id" :placeholder="__('— None —')" col="col-md-4" />
        <x-form.select name="helper_id" :label="__('Helper')" :options="$helpers" :value="$route->helper_id" :placeholder="__('— None —')" col="col-md-4" />
        <x-form.input name="monthly_fee" type="number" step="0.01" min="0" :label="__('Default monthly fee (৳)')" :value="$route->monthly_fee ?? 0" required col="col-md-4" :help="__('Used by stops that have no fee of their own')" />
        <x-form.input name="morning_start" type="time" :label="__('Morning start')" :value="$route->morning_start ? substr($route->morning_start, 0, 5) : null" col="col-md-4" />
        <x-form.input name="afternoon_start" type="time" :label="__('Afternoon start')" :value="$route->afternoon_start ? substr($route->afternoon_start, 0, 5) : null" col="col-md-4" />
        <div class="col-12"><label class="form-label" for="description">{{ __('Description') }}</label><textarea id="description" name="description" rows="2" maxlength="2000" class="form-control">{{ old('description', $route->description) }}</textarea></div>
        <x-form.switch name="is_active" :label="__('In service')" :checked="$route->exists ? $route->is_active : true" />
    </div>
    <x-form.actions :cancel="route('branch.transport.routes.index')" />
</form>
@endsection
