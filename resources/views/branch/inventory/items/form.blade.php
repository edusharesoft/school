@extends('layouts.branch')
@section('title', $item ? __('Edit item') : __('New item'))

@section('content')
<x-page-header :title="$item ? __('Edit item') : __('New item')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Items & stock') => route('branch.inventory.items.index'), ($item ? $item->name : __('New item')) => null]" />
<form method="POST" action="{{ $item ? route('branch.inventory.items.update', $item) : route('branch.inventory.items.store') }}" class="card" style="max-width: 900px">
    @csrf
    @if($item) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$item?->name" required maxlength="150" />
        <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$item?->name_bn" maxlength="150" />
        <x-form.select name="inventory_category_id" :label="__('Category')" :options="$categories" :value="$item?->inventory_category_id" required :placeholder="__('Choose')" col="col-md-4" />
        <x-form.select name="item_type" :label="__('Type')" col="col-md-4" :value="$item?->item_type ?? 'consumable'"
            :options="['consumable' => __('Consumable (used up: paper, chalk, chemicals)'), 'durable' => __('Durable equipment (kept: microscopes, tools)')]" />
        <x-form.input name="unit" :label="__('Unit')" :value="$item?->unit ?? 'pcs'" required maxlength="20" col="col-md-2" :help="__('pcs, kg, litre, ream …')" />
        <x-form.input name="reorder_level" type="number" step="0.001" min="0" :label="__('Reorder level')" :value="$item?->reorder_level ?? 0" col="col-md-2" :help="__('0 = no alert')" />
        <x-form.input name="location" :label="__('Shelf / store')" :value="$item?->location" maxlength="100" col="col-md-6" />
        <x-form.input name="description" :label="__('Description')" :value="$item?->description" maxlength="255" col="col-md-6" />
        @unless($item)
            @can('inventory.approve')
            <div class="col-12"><hr class="my-1"><div class="small text-body-secondary mb-2">{{ __('Opening stock (optional) — posted as a stock adjustment against the opening balance.') }}</div></div>
            <x-form.input name="opening_quantity" type="number" step="0.001" min="0" :label="__('Opening quantity')" col="col-md-3" />
            <x-form.input name="opening_unit_cost" type="number" step="0.01" min="0" :label="__('Unit cost (৳)')" col="col-md-3" />
            @endcan
        @endunless
        <x-form.switch name="is_active" :label="__('Active')" :checked="$item?->is_active ?? true" />
    </div>
    <x-form.actions :cancel="$item ? route('branch.inventory.items.show', $item) : route('branch.inventory.items.index')" />
</form>
@endsection
