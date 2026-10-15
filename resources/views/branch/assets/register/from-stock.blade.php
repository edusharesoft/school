@extends('layouts.branch')
@section('title', __('Move stock to the asset register'))

@section('content')
@php
    $defaults = $categories->mapWithKeys(fn ($c) => [$c->id => ['method' => $c->depreciation_method, 'life' => $c->useful_life_months, 'rate' => (float) $c->declining_rate]]);
@endphp
<x-page-header :title="__('Move stock to the asset register')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Asset register') => route('branch.assets.register.index'), __('Move from stock') => null]" />
<div class="alert alert-light border small">{{ __('Durable equipment received through goods receiving sits in inventory at its cost. Moving it here takes one unit out of stock for every asset (at the average cost) and records it as a fixed asset — the books move Inventory stock → Fixed assets.') }}</div>
@if($items->isEmpty())
    <div class="card"><x-empty-state icon="box-seam" :title="__('No durable item has stock')" :message="__('Receive durable equipment through Inventory → Goods receiving first.')" /></div>
@else
<form method="POST" action="{{ route('branch.assets.register.from-stock.store') }}" class="card" style="max-width: 1000px"
      x-data="{ defaults: {{ Illuminate\Support\Js::from($defaults) }}, method: '{{ old('depreciation_method', '') }}', life: '{{ old('useful_life_months', '') }}', rate: '{{ old('declining_rate', '') }}', category: '{{ old('asset_category_id') }}',
                pick() { const d = this.defaults[this.category]; if (!d) return; this.method = ''; this.life = ''; this.rate = ''; } }">
    @csrf
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label" for="inventory_item_id">{{ __('Durable item') }} <span class="text-danger">*</span></label>
            <select id="inventory_item_id" name="inventory_item_id" class="form-select @error('inventory_item_id') is-invalid @enderror" required>
                <option value="">{{ __('Choose') }}</option>@foreach($items as $i)<option value="{{ $i->id }}" @selected((int) old('inventory_item_id', $selected) === $i->id)>{{ $i->code }} — {{ $i->name }} ({{ rtrim(rtrim(number_format($i->quantity_on_hand, 3, '.', ''), '0'), '.') }} {{ $i->unit }} · ৳{{ number_format($i->averageCost(), 2) }})</option>@endforeach</select>
            @error('inventory_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <x-form.input name="quantity" type="number" min="1" max="200" :label="__('Units to move')" :value="1" required col="col-md-3" />
        <x-form.input name="acquired_on" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-md-3" />
        <div class="col-md-4"><label class="form-label" for="asset_category_id">{{ __('Asset category') }} <span class="text-danger">*</span></label>
            <select id="asset_category_id" name="asset_category_id" class="form-select @error('asset_category_id') is-invalid @enderror" required x-model="category" @change="pick()"><option value="">{{ __('Choose') }}</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
            @error('asset_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Depreciation follows the category’s defaults.') }}</div></div>
        <x-form.input name="name" :label="__('Asset name (blank = the item’s name)')" maxlength="150" col="col-md-4" />
        <x-form.select name="condition" :label="__('Condition')" col="col-md-4" :value="'new'" :options="collect(App\Models\Assets\Asset::CONDITIONS)->mapWithKeys(fn ($c) => [$c => __(ucfirst($c))])->all()" />
        <x-form.select name="room_id" :label="__('Room')" :options="$rooms" :placeholder="__('— none —')" col="col-md-4" />
        <x-form.input name="location" :label="__('Place in the room / store')" maxlength="100" col="col-md-4" />
        <x-form.input name="warranty_expires_on" type="date" :label="__('Warranty ends')" col="col-md-4" />
        <x-form.input name="safety_notes" :label="__('Safety notes')" maxlength="255" col="col-md-6" />
        <x-form.input name="description" :label="__('Description')" maxlength="255" col="col-md-6" />
        <div class="col-12"><label class="form-label" for="serials">{{ __('Serial numbers (one per line, optional)') }}</label><textarea id="serials" name="serials" rows="3" class="form-control @error('serials') is-invalid @enderror">{{ old('serials') }}</textarea>@error('serials')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
    <x-form.actions :cancel="route('branch.assets.register.index')">{{ __('Move to the register') }}</x-form.actions>
</form>
@endif
@endsection
