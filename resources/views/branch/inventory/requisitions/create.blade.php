@extends('layouts.branch')
@section('title', __('New purchase request'))

@section('content')
<x-page-header :title="__('New purchase request')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Purchase requests') => route('branch.inventory.requisitions.index'), __('New request') => null]" />
<form method="POST" action="{{ route('branch.inventory.requisitions.store') }}" class="card" style="max-width: 1000px">@csrf
    <div class="card-body row g-3">
        <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.input name="needed_by" type="date" :label="__('Needed by')" col="col-md-3" />
        <x-form.select name="department_id" :label="__('Department')" :options="$departments" :placeholder="__('— none —')" col="col-md-6" />
        <x-form.input name="purpose" :label="__('Purpose')" required maxlength="255" col="col-12" :placeholder="__('e.g. Chemistry practical, new term stationery')" />
        <div class="col-12">@include('branch.inventory.partials.lines', ['lines' => $lines, 'items' => $items, 'cols' => [['key' => 'quantity', 'label' => __('Quantity'), 'step' => '0.001'], ['key' => 'estimated_unit_cost', 'label' => __('Estimated cost (৳)'), 'step' => '0.01']], 'costKey' => 'estimated_unit_cost'])</div>
    </div>
    <x-form.actions :cancel="route('branch.inventory.requisitions.index')">{{ __('Send for approval') }}</x-form.actions>
</form>
@endsection
