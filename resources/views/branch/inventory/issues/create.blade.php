@extends('layouts.branch')
@section('title', __('Issue stock'))

@section('content')
<x-page-header :title="__('Issue stock')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Stock issues') => route('branch.inventory.issues.index'), __('Issue stock') => null]" />
<form method="POST" action="{{ route('branch.inventory.issues.store') }}" class="card" style="max-width: 1000px">@csrf
    <div class="card-body row g-3">
        <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.select name="department_id" :label="__('Department')" :options="$departments" :placeholder="__('— none —')" col="col-md-3" />
        <x-form.select name="employee_id" :label="__('Employee')" :options="$employees" :placeholder="__('— none —')" col="col-md-3" />
        <x-form.input name="recipient" :label="__('Or a name / place')" maxlength="150" col="col-md-3" :placeholder="__('e.g. Science lab')" />
        <x-form.input name="purpose" :label="__('Purpose')" maxlength="255" col="col-12" />
        <div class="col-12">@include('branch.inventory.partials.lines', ['lines' => $lines, 'items' => $items, 'cols' => [['key' => 'quantity', 'label' => __('Quantity'), 'step' => '0.001', 'min' => '0']]])
            <div class="form-text mt-2">{{ __('Only consumables with stock are listed. The cost is taken from stock at its average cost and charged to the item category’s expense account.') }}</div></div>
    </div>
    <x-form.actions :cancel="route('branch.inventory.issues.index')">{{ __('Issue') }}</x-form.actions>
</form>
@endsection
