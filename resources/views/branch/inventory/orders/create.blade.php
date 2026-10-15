@extends('layouts.branch')
@section('title', __('New purchase order'))

@section('content')
<x-page-header :title="__('New purchase order')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Purchase orders') => route('branch.inventory.orders.index'), __('New order') => null]" />
<form method="POST" action="{{ route('branch.inventory.orders.store') }}" class="card" style="max-width: 1000px">@csrf
    @if($requisition)<input type="hidden" name="inventory_requisition_id" value="{{ $requisition->id }}">@endif
    <div class="card-body row g-3">
        @if($requisition)<div class="col-12"><div class="alert alert-info small mb-0">{{ __('From purchase request :no — lines are filled in from it; set the real unit cost.', ['no' => $requisition->requisition_no]) }}</div></div>@endif
        <x-form.select name="vendor_id" :label="__('Supplier')" :options="$vendors" required :placeholder="__('Choose')" col="col-md-6" />
        <x-form.input name="order_date" type="date" :label="__('Order date')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.input name="expected_on" type="date" :label="__('Expected on')" col="col-md-3" />
        <x-form.input name="notes" :label="__('Notes to the supplier')" maxlength="255" col="col-12" />
        <div class="col-12">@include('branch.inventory.partials.lines', ['lines' => $lines, 'items' => $items, 'cols' => [['key' => 'quantity', 'label' => __('Quantity'), 'step' => '0.001'], ['key' => 'unit_cost', 'label' => __('Unit cost (৳)'), 'step' => '0.01']], 'costKey' => 'unit_cost'])</div>
    </div>
    <x-form.actions :cancel="route('branch.inventory.orders.index')">{{ __('Save as draft') }}</x-form.actions>
</form>
@endsection
