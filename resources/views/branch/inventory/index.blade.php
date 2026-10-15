@extends('layouts.branch')
@section('title', __('Inventory'))

@section('content')
<x-page-header :title="__('Inventory')" :breadcrumbs="[__('Inventory') => null]">
    <x-slot:actions>
        @can('inventory.create')<a href="{{ route('branch.inventory.issues.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-dolly me-1"></i>{{ __('Issue stock') }}</a>
        <a href="{{ route('branch.inventory.receipts.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-truck me-1"></i>{{ __('Receive goods') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Stock value')" :value="money_bdt($value)" icon="fa-solid fa-sack-dollar" bg="#dcfce7" color="#15803d" :sub="__(':n items', ['n' => $items])" :href="route('branch.inventory.reports.stock')" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Low stock')" :value="$low" icon="fa-solid fa-triangle-exclamation" bg="#fee2e2" color="#b91c1c" :sub="__('at or below reorder level')" :href="route('branch.inventory.items.index', ['status' => 'low'])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Open requests & orders')" :value="$pendingRequests + $openOrders + $draftOrders" icon="fa-solid fa-hourglass-half" bg="#fef3c7" color="#b45309" :sub="__(':a to approve · :b to receive', ['a' => $pendingRequests + $draftOrders, 'b' => $openOrders])" :href="route('branch.inventory.orders.index', ['status' => 'approved'])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Owed to suppliers')" :value="money_bdt($payable)" icon="fa-solid fa-file-invoice-dollar" bg="#e0e7ff" color="#4338ca" :sub="__('goods received on credit')" :href="route('branch.inventory.receipts.index', ['due' => 1])" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header bg-transparent fw-semibold">{{ __('Running low') }}</div>
        @if($lowItems->isEmpty())<x-empty-state icon="check2-circle" :title="__('Nothing is below its reorder level')" />
        @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Reorder at') }}</th></tr></thead>
            <tbody>@foreach($lowItems as $i)<tr><td><a href="{{ route('branch.inventory.items.show', $i) }}" class="text-decoration-none">{{ $i->displayName() }}</a><div class="text-body-secondary">{{ $i->category?->name }}</div></td>
                <td class="text-end fw-semibold text-danger">{{ rtrim(rtrim(number_format($i->quantity_on_hand, 3, '.', ''), '0'), '.') ?: '0' }} {{ $i->unit }}</td><td class="text-end">{{ rtrim(rtrim(number_format($i->reorder_level, 3, '.', ''), '0'), '.') }}</td></tr>@endforeach</tbody></table></div>@endif
    </div></div>
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header bg-transparent fw-semibold">{{ __('Latest stock movements') }}</div>
        @if($recent->isEmpty())<x-empty-state icon="arrow-left-right" :title="__('No stock movements yet')" />
        @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Item') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Quantity') }}</th></tr></thead>
            <tbody>@foreach($recent as $t)<tr><td class="text-nowrap">{{ $t->date->format('d M') }}</td><td>{{ $t->item?->name }}<div class="text-body-secondary">{{ $t->reference }}</div></td><td>{{ __(ucfirst($t->type)) }}</td>
                <td class="text-end fw-semibold @if($t->quantity < 0) text-danger @else text-success @endif">{{ $t->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($t->quantity, 3, '.', ''), '0'), '.') }}</td></tr>@endforeach</tbody></table></div>@endif
    </div></div>
</div>
@endsection
