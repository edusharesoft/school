@extends('layouts.branch')
@section('title', __('Stock & valuation'))

@section('content')
<x-page-header :title="__('Inventory reports')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Reports') => null]" />
@include('branch.inventory.reports._nav')
<div class="row g-3">
    <div class="col-lg-4"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Value by category') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Items') }}</th><th class="text-end">{{ __('Value (৳)') }}</th></tr></thead>
            <tbody>@forelse($byCategory as $c)<tr><td>{{ $c['category'] }}</td><td class="text-end">{{ $c['items'] }}</td><td class="text-end">{{ number_format($c['value'], 2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No stock yet') }}</td></tr>@endforelse</tbody>
            <tfoot><tr class="fw-semibold"><td>{{ __('Total') }}</td><td class="text-end">{{ $byCategory->sum('items') }}</td><td class="text-end">{{ number_format($byCategory->sum('value'), 2) }}</td></tr></tfoot></table></div></div></div>
    <div class="col-lg-8"><div class="card">
        <div class="card-body border-bottom"><form method="GET" class="row g-2">
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name or code') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-4"><select name="category" class="form-select form-select-sm" aria-label="{{ __('Category') }}"><option value="">{{ __('All categories') }}</option>@foreach($categories as $id => $name)<option value="{{ $id }}" @selected((int) request('category') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Stock') }}"><option value="">{{ __('All stock levels') }}</option><option value="low" @selected(request('status') === 'low')>{{ __('Low stock') }}</option><option value="out" @selected(request('status') === 'out')>{{ __('Out of stock') }}</option></select></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div></form></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Reorder at') }}</th><th class="text-end">{{ __('Average cost') }}</th><th class="text-end">{{ __('Value (৳)') }}</th></tr></thead>
            <tbody>@forelse($items as $i)<tr><td><a href="{{ route('branch.inventory.items.show', $i) }}" class="text-decoration-none">{{ $i->displayName() }}</a><div class="text-body-secondary">{{ $i->code }} · {{ $i->category?->name }}</div></td>
                <td class="text-end @if($i->isLow()) text-danger fw-semibold @endif">{{ rtrim(rtrim(number_format($i->quantity_on_hand, 3, '.', ''), '0'), '.') ?: '0' }} {{ $i->unit }}</td><td class="text-end">{{ $i->reorder_level > 0 ? rtrim(rtrim(number_format($i->reorder_level, 3, '.', ''), '0'), '.') : '—' }}</td><td class="text-end">{{ number_format($i->averageCost(), 2) }}</td><td class="text-end">{{ number_format($i->stock_value, 2) }}</td></tr>
                @empty<tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No items found') }}</td></tr>@endforelse</tbody>
            <tfoot><tr class="fw-semibold"><td colspan="4" class="text-end">{{ __('Total of the filtered list') }}</td><td class="text-end">{{ number_format($totals['value'], 2) }}</td></tr></tfoot></table></div>
        <div class="card-body">{{ $items->links() }}</div>
    </div></div>
</div>
@endsection
