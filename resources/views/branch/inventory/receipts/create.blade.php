@extends('layouts.branch')
@section('title', __('Receive goods'))

@section('content')
<x-page-header :title="__('Receive goods')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Goods receiving') => route('branch.inventory.receipts.index'), __('Receive goods') => null]" />
@if(! $order && $openOrders->isNotEmpty())
<div class="alert alert-light border small d-flex flex-wrap gap-2 align-items-center" style="max-width: 1000px"><span>{{ __('Against an approved order:') }}</span>
    @foreach($openOrders as $o)<a href="{{ route('branch.inventory.receipts.create', ['order' => $o->id]) }}" class="btn btn-sm btn-outline-primary">{{ $o->po_no }} · {{ $o->vendor?->name }}</a>@endforeach</div>
@endif
<form method="POST" action="{{ route('branch.inventory.receipts.store') }}" class="card" style="max-width: 1000px" x-data="{ mode: '{{ old('payment_mode', 'credit') }}' }">@csrf
    <div class="card-body row g-3">
        @if($order)
            <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">
            <div class="col-12"><div class="alert alert-info small mb-0">{{ __('Receiving against :no from :supplier. Reduce the quantity if only part of the order arrived.', ['no' => $order->po_no, 'supplier' => $order->vendor?->name]) }}</div></div>
        @else
            <x-form.select name="vendor_id" :label="__('Supplier')" :options="$vendors" required :placeholder="__('Choose')" col="col-md-6" />
        @endif
        <x-form.input name="received_on" type="date" :label="__('Received on')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.input name="bill_no" :label="__('Supplier bill / challan no.')" maxlength="60" col="col-md-3" />
        <x-form.select name="payment_mode" :label="__('Payment')" required col="col-md-4" x-model="mode" :options="['credit' => __('On credit — pay the supplier later'), 'paid' => __('Paid now')]" :value="old('payment_mode', 'credit')" />
        <div class="col-md-4" x-show="mode === 'paid'" x-cloak>
            <label class="form-label" for="paid_from_account_id">{{ __('Paid from') }}</label>
            <select id="paid_from_account_id" name="paid_from_account_id" class="form-select @error('paid_from_account_id') is-invalid @enderror"><option value="">—</option>@foreach($moneyAccounts as $id => $name)<option value="{{ $id }}" @selected((int) old('paid_from_account_id') === $id)>{{ $name }}</option>@endforeach</select>
            @error('paid_from_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-form.input name="note" :label="__('Note')" maxlength="255" col="col-md-4" />
        <div class="col-12">@include('branch.inventory.partials.lines', ['lines' => $lines, 'items' => $items, 'cols' => [['key' => 'quantity', 'label' => __('Quantity received'), 'step' => '0.001'], ['key' => 'unit_cost', 'label' => __('Unit cost (৳)'), 'step' => '0.01']], 'costKey' => 'unit_cost', 'hidden' => $order ? ['purchase_order_item_id'] : [], 'locked' => (bool) $order])</div>
    </div>
    <x-form.actions :cancel="route('branch.inventory.receipts.index')">{{ __('Post receipt') }}</x-form.actions>
</form>
@endsection
