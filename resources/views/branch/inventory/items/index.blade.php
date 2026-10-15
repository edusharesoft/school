@extends('layouts.branch')
@section('title', __('Items & stock'))

@section('content')
<x-page-header :title="__('Items & stock')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Items & stock') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.inventory.categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1"></i>{{ __('Categories') }}</a>
        @can('inventory.create')<a href="{{ route('branch.inventory.items.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New item') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name or code') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-3"><select name="category" class="form-select form-select-sm" aria-label="{{ __('Category') }}"><option value="">{{ __('All categories') }}</option>@foreach($categories as $id => $name)<option value="{{ $id }}" @selected((int) request('category') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Stock') }}"><option value="">{{ __('All stock levels') }}</option><option value="low" @selected(request('status') === 'low')>{{ __('Low stock') }}</option><option value="out" @selected(request('status') === 'out')>{{ __('Out of stock') }}</option></select></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
            <div class="col text-md-end small text-body-secondary">{{ __(':n items · value :v', ['n' => $totals['items'], 'v' => money_bdt($totals['value'])]) }}</div>
        </form>
    </div>
    @if($items->isEmpty())
        <x-empty-state icon="box-seam" :title="__('No items found')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Item') }}</th><th>{{ __('Category') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Average cost') }}</th><th class="text-end">{{ __('Value (৳)') }}</th></tr></thead>
            <tbody>@foreach($items as $i)
                <tr>
                    <td class="text-nowrap text-body-secondary">{{ $i->code }}</td>
                    <td><a href="{{ route('branch.inventory.items.show', $i) }}" class="fw-semibold text-decoration-none">{{ $i->displayName() }}</a>@if($i->location)<div class="text-body-secondary">{{ $i->location }}</div>@endif</td>
                    <td>{{ $i->category?->name }}</td>
                    <td>{{ __(ucfirst($i->item_type)) }}</td>
                    <td class="text-end fw-semibold @if($i->isLow()) text-danger @endif">{{ rtrim(rtrim(number_format($i->quantity_on_hand, 3, '.', ''), '0'), '.') ?: '0' }} {{ $i->unit }}@if($i->isLow())<div class="small"><i class="bi bi-exclamation-triangle"></i> {{ __('reorder') }}</div>@endif</td>
                    <td class="text-end">{{ number_format($i->averageCost(), 2) }}</td>
                    <td class="text-end">{{ number_format($i->stock_value, 2) }}</td>
                </tr>
            @endforeach</tbody></table></div>
        <div class="card-body">{{ $items->links() }}</div>
    @endif
</div>
@endsection
