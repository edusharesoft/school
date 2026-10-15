@extends('layouts.branch')
@section('title', __('New stock adjustment'))

@section('content')
<x-page-header :title="__('New stock adjustment')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Stock adjustments') => route('branch.inventory.adjustments.index'), __('New adjustment') => null]" />
<form method="POST" action="{{ route('branch.inventory.adjustments.store') }}" class="card" style="max-width: 1000px">@csrf
    <div class="card-body row g-3">
        <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.select name="reason" :label="__('Reason')" required col="col-md-3" :placeholder="__('Choose')"
            :options="['opening' => __('Opening stock'), 'damage' => __('Damaged'), 'loss' => __('Lost / missing'), 'expired' => __('Expired'), 'found' => __('Found'), 'recount' => __('Recount'), 'other' => __('Other')]" />
        <x-form.input name="note" :label="__('Note')" maxlength="255" col="col-md-6" />
        <div class="col-12">@include('branch.inventory.partials.lines', ['lines' => $lines, 'items' => $items, 'cols' => [['key' => 'quantity', 'label' => __('Change (+ adds, − removes)'), 'step' => '0.001'], ['key' => 'unit_cost', 'label' => __('Unit cost (৳) — only for additions'), 'step' => '0.01']]])
            <div class="form-text mt-2">{{ __('Removals are valued at the average cost. Additions use the unit cost you enter (the current average if left empty). Opening stock is posted against the opening balance; other changes go to “Stock adjustments”.') }}</div></div>
    </div>
    <x-form.actions :cancel="route('branch.inventory.adjustments.index')">{{ __('Post adjustment') }}</x-form.actions>
</form>
@endsection
