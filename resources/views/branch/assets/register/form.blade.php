@extends('layouts.branch')
@section('title', $asset ? __('Edit asset') : __('Register an asset'))

@section('content')
@php
    $defaults = $categories->mapWithKeys(fn ($c) => [$c->id => ['method' => $c->depreciation_method, 'life' => $c->useful_life_months, 'rate' => (float) $c->declining_rate, 'salvage' => (float) $c->salvage_percent, 'lab' => $c->kind === 'laboratory']]);
    $methods = ['straight_line' => __('Straight line'), 'declining_balance' => __('Declining balance'), 'none' => __('None (land, art …)')];
    $canApprove = auth()->user()->can('assets.approve');
@endphp
<x-page-header :title="$asset ? __('Edit asset') : __('Register an asset')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Asset register') => route('branch.assets.register.index'), ($asset ? $asset->code : __('New')) => null]" />
<form method="POST" action="{{ $asset ? route('branch.assets.register.update', $asset) : route('branch.assets.register.store') }}" class="card" style="max-width: 1000px"
      x-data="{ defaults: {{ Illuminate\Support\Js::from($defaults) }}, category: '{{ old('asset_category_id', $asset?->asset_category_id) }}', type: '{{ old('acquisition_type', $asset?->acquisition_type ?? 'purchase') }}',
                method: '{{ old('depreciation_method', $asset?->depreciation_method ?? 'straight_line') }}', life: '{{ old('useful_life_months', $asset?->useful_life_months ?? '') }}', rate: '{{ old('declining_rate', $asset?->declining_rate ?? '') }}',
                salvage: '{{ old('salvage_value', $asset?->salvage_value ?? '') }}', cost: '{{ old('cost', $asset?->cost ?? '') }}',
                pick() { const d = this.defaults[this.category]; if (!d || {{ $asset ? 'true' : 'false' }}) return; this.method = d.method; this.life = d.life; this.rate = d.rate || ''; this.salvage = this.cost ? (this.cost * d.salvage / 100).toFixed(2) : ''; },
                money() { if (!{{ $asset ? 'true' : 'false' }} && this.category && !this.salvage) { const d = this.defaults[this.category]; if (d && d.salvage) this.salvage = (this.cost * d.salvage / 100).toFixed(2); } } }">
    @csrf
    @if($asset) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$asset?->name" required maxlength="150" />
        <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$asset?->name_bn" maxlength="150" />
        <div class="col-md-4">
            <label class="form-label" for="asset_category_id">{{ __('Category') }} <span class="text-danger">*</span></label>
            <select id="asset_category_id" name="asset_category_id" class="form-select @error('asset_category_id') is-invalid @enderror" required x-model="category" @change="pick()">
                <option value="">{{ __('Choose') }}</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
            @error('asset_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-form.input name="serial_no" :label="__('Serial number')" :value="$asset?->serial_no" maxlength="80" col="col-md-4" />
        <x-form.select name="condition" :label="__('Condition')" col="col-md-4" :value="$asset?->condition ?? 'new'" :options="collect(App\Models\Assets\Asset::CONDITIONS)->mapWithKeys(fn ($c) => [$c => __(ucfirst($c))])->all()" />
        <x-form.input name="brand" :label="__('Brand')" :value="$asset?->brand" maxlength="80" col="col-md-4" />
        <x-form.input name="model" :label="__('Model')" :value="$asset?->model" maxlength="80" col="col-md-4" />
        <x-form.select name="vendor_id" :label="__('Supplier')" :options="$vendors" :value="$asset?->vendor_id" :placeholder="__('— none —')" col="col-md-4" />
        <x-form.select name="room_id" :label="__('Room')" :options="$rooms" :value="$asset?->room_id" :placeholder="__('— none —')" col="col-md-4" />
        <x-form.input name="location" :label="__('Place in the room / store')" :value="$asset?->location" maxlength="100" col="col-md-4" />
        <x-form.input name="warranty_expires_on" type="date" :label="__('Warranty ends')" :value="$asset?->warranty_expires_on?->toDateString()" col="col-md-4" />
        <x-form.input name="safety_notes" :label="__('Safety notes (laboratory equipment)')" :value="$asset?->safety_notes" maxlength="255" col="col-md-8" />
        <x-form.input name="description" :label="__('Description')" :value="$asset?->description" maxlength="255" col="col-md-8" />
        <x-form.input name="bill_no" :label="__('Bill / invoice no.')" :value="$asset?->bill_no" maxlength="60" col="col-md-4" />

        @unless($asset)
        <div class="col-12"><hr class="my-1"><div class="fw-semibold small mb-1">{{ __('How was it acquired?') }}</div></div>
        <div class="col-md-4">
            <label class="form-label" for="acquisition_type">{{ __('Acquisition') }}</label>
            <select id="acquisition_type" name="acquisition_type" class="form-select" x-model="type">
                <option value="purchase">{{ __('Bought and paid now') }}</option>
                <option value="donation">{{ __('Donated / gift') }}</option>
                @if($canApprove)<option value="opening">{{ __('Already owned (opening balance)') }}</option>@endif
            </select>
            <div class="form-text" x-show="type === 'purchase'">{{ __('Bought on credit? Receive it through Inventory → Goods receiving, then use “Move from stock”.') }}</div>
        </div>
        <x-form.input name="cost" type="number" step="0.01" min="0.01" :label="__('Cost per unit (৳)')" required col="col-md-3" x-model="cost" @input="money()" />
        <x-form.input name="acquired_on" type="date" :label="__('Acquired on')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.input name="quantity" type="number" min="1" max="50" :label="__('How many?')" :value="1" col="col-md-2" :help="__('identical units')" />
        <div class="col-md-4" x-show="type === 'purchase'"><label class="form-label" for="paid_from_account_id">{{ __('Paid from') }}</label>
            <select id="paid_from_account_id" name="paid_from_account_id" class="form-select @error('paid_from_account_id') is-invalid @enderror"><option value="">{{ __('Choose') }}</option>@foreach($moneyAccounts as $id => $label)<option value="{{ $id }}" @selected((string) old('paid_from_account_id') === (string) $id)>{{ $label }}</option>@endforeach</select>
            @error('paid_from_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <template x-if="type === 'opening'">
            <div class="row g-3 m-0 p-0">
                <x-form.input name="opening_accumulated" type="number" step="0.01" min="0" :label="__('Depreciation already charged (৳)')" col="col-md-4" />
                <x-form.input name="opening_date" type="date" :label="__('Books opened on')" :value="now()->toDateString()" col="col-md-4" />
                <x-form.input name="depreciation_from" :label="__('Charge depreciation from (YYYY-MM)')" :value="now()->format('Y-m')" col="col-md-4" maxlength="7" />
            </div>
        </template>
        @endunless

        <div class="col-12"><hr class="my-1"><div class="fw-semibold small mb-1">{{ __('Depreciation') }}</div>
            @if($locked)<div class="alert alert-light border small mb-0">{{ __('Depreciation has been posted for this asset, so these settings are fixed. Reverse the depreciation runs to change them.') }}</div>@endif</div>
        @if(! $asset || ($canApprove && ! $locked))
        <div class="col-md-3"><label class="form-label" for="depreciation_method">{{ __('Method') }}</label>
            <select id="depreciation_method" name="depreciation_method" class="form-select" x-model="method">@foreach($methods as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3" x-show="method === 'straight_line'"><label class="form-label" for="useful_life_months">{{ __('Useful life (months)') }}</label><input id="useful_life_months" name="useful_life_months" type="number" min="0" max="1200" class="form-control" x-model="life"></div>
        <div class="col-md-3" x-show="method === 'declining_balance'"><label class="form-label" for="declining_rate">{{ __('Yearly rate %') }}</label><input id="declining_rate" name="declining_rate" type="number" step="0.01" min="0" max="100" class="form-control" x-model="rate"></div>
        <div class="col-md-3" x-show="method !== 'none'"><label class="form-label" for="salvage_value">{{ __('Salvage value (৳)') }}</label><input id="salvage_value" name="salvage_value" type="number" step="0.01" min="0" class="form-control" x-model="salvage"></div>
        <x-form.input name="in_service_on" type="date" :label="__('In service from')" :value="$asset?->in_service_on?->toDateString()" col="col-md-3" :help="__('blank = acquisition date')" />
        @if($asset && $asset->acquisition_type === 'opening')<x-form.input name="depreciation_from" :label="__('Charge depreciation from (YYYY-MM)')" :value="$asset->depreciation_from" col="col-md-3" maxlength="7" />@endif
        @else
        <div class="col-12 small text-body-secondary">{{ $asset ? __('Method: :m · life :l months · salvage ৳:s', ['m' => ucwords(str_replace('_', ' ', $asset->depreciation_method)), 'l' => $asset->useful_life_months ?: '—', 's' => number_format($asset->salvage_value, 2)]) : '' }}</div>
        @endif
    </div>
    <x-form.actions :cancel="$asset ? route('branch.assets.register.show', $asset) : route('branch.assets.register.index')">{{ $asset ? __('Save') : __('Register') }}</x-form.actions>
</form>
@endsection
