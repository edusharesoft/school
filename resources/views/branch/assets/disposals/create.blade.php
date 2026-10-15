@extends('layouts.branch')
@section('title', __('Dispose of an asset'))

@section('content')
<x-page-header :title="__('Dispose of :name', ['name' => $asset->displayName()])" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Asset register') => route('branch.assets.register.index'), $asset->code => route('branch.assets.register.show', $asset), __('Dispose') => null]" />
<div class="alert alert-light border small">
    {{ __('Cost ৳:c, depreciation so far ৳:a, book value ৳:b.', ['c' => number_format($asset->cost, 2), 'a' => number_format($asset->accumulated_depreciation, 2), 'b' => number_format($asset->bookValue(), 2)]) }}
    @if($catchUp > 0) {{ __('Depreciation not yet charged (about ৳:x if disposed today) is posted first, so the book value drops to about ৳:y.', ['x' => number_format($catchUp, 2), 'y' => number_format($asset->bookValue() - $catchUp, 2)]) }}@endif
</div>
<form method="POST" action="{{ route('branch.assets.disposals.store', $asset) }}" class="card" style="max-width: 800px" x-data="{ type: '{{ old('type', 'sale') }}' }">@csrf
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label" for="type">{{ __('How does it leave?') }}</label>
            <select id="type" name="type" class="form-select" x-model="type"><option value="sale">{{ __('Sold') }}</option><option value="scrap">{{ __('Scrapped / thrown away') }}</option><option value="donated">{{ __('Given away') }}</option><option value="lost">{{ __('Lost or stolen') }}</option></select></div>
        <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-md-4" />
        <div class="col-md-4" x-show="type === 'sale' || type === 'scrap'"><label class="form-label" for="proceeds">{{ __('Money received (৳)') }}</label><input id="proceeds" name="proceeds" type="number" step="0.01" min="0" value="{{ old('proceeds') }}" class="form-control @error('proceeds') is-invalid @enderror">@error('proceeds')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6" x-show="type === 'sale' || type === 'scrap'"><label class="form-label" for="account_id">{{ __('Received into') }}</label>
            <select id="account_id" name="account_id" class="form-select @error('account_id') is-invalid @enderror"><option value="">{{ __('Choose') }}</option>@foreach($moneyAccounts as $id => $label)<option value="{{ $id }}" @selected((string) old('account_id') === (string) $id)>{{ $label }}</option>@endforeach</select>@error('account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <x-form.input name="buyer" :label="__('Buyer / recipient')" maxlength="150" col="col-md-6" />
        <x-form.input name="reason" :label="__('Reason')" maxlength="255" col="col-12" />
        @error('asset')<div class="col-12"><div class="alert alert-danger small mb-0">{{ $message }}</div></div>@enderror
    </div>
    <x-form.actions :cancel="route('branch.assets.register.show', $asset)">{{ __('Post disposal') }}</x-form.actions>
</form>
@endsection
