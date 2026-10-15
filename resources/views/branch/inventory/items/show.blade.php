@extends('layouts.branch')
@section('title', $item->name)

@section('content')
<x-page-header :title="$item->displayName()" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Items & stock') => route('branch.inventory.items.index'), $item->code => null]">
    <x-slot:actions>
        @if($item->item_type === 'durable' && $item->quantity_on_hand > 0)@can('assets.create')<a href="{{ route('branch.assets.register.from-stock', ['item' => $item->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>{{ __('Move to asset register') }}</a>@endcan @endif
        @can('inventory.update')<a href="{{ route('branch.inventory.items.edit', $item) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>@endcan
        @can('inventory.delete')<form method="POST" action="{{ route('branch.inventory.items.destroy', $item) }}" class="d-inline" data-confirm="{{ __('Delete this item?') }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>@endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card :label="__('On hand')" :value="rtrim(rtrim(number_format($item->quantity_on_hand, 3, '.', ''), '0'), '.') . ' ' . $item->unit" icon="fa-solid fa-box-open" bg="#dbeafe" color="#1d4ed8" :sub="$item->isLow() ? __('At or below reorder level') : null" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Average cost')" :value="money_bdt($item->averageCost())" icon="fa-solid fa-tag" bg="#fef3c7" color="#b45309" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Stock value')" :value="money_bdt($item->stock_value)" icon="fa-solid fa-sack-dollar" bg="#dcfce7" color="#15803d" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Reorder level')" :value="rtrim(rtrim(number_format($item->reorder_level, 3, '.', ''), '0'), '.') ?: '—'" icon="fa-solid fa-bell" bg="#fee2e2" color="#b91c1c" :sub="$item->category?->name . ' · ' . __(ucfirst($item->item_type))" /></div>
</div>

<div class="card">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between gap-2 align-items-center">
        <span class="fw-semibold">{{ __('Stock card') }}</span>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" aria-label="{{ __('From') }}">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" aria-label="{{ __('To') }}">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reference') }}</th><th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th><th class="text-end">{{ __('Value (৳)') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ __('Balance value (৳)') }}</th></tr></thead>
        <tbody>
            <tr class="table-light"><td colspan="6">{{ __('Brought forward') }}</td><td class="text-end">{{ rtrim(rtrim(number_format($card['openingQuantity'], 3, '.', ''), '0'), '.') ?: '0' }}</td><td class="text-end">{{ number_format($card['openingValue'], 2) }}</td></tr>
            @forelse($card['lines'] as $t)
                <tr>
                    <td class="text-nowrap">{{ $t->date->format('d M Y') }}</td><td>{{ __(ucfirst($t->type)) }}</td><td>{{ $t->reference }}<div class="text-body-secondary">{{ $t->note }}</div></td>
                    <td class="text-end">{{ $t->quantity > 0 ? rtrim(rtrim(number_format($t->quantity, 3, '.', ''), '0'), '.') : '' }}</td>
                    <td class="text-end">{{ $t->quantity < 0 ? rtrim(rtrim(number_format(abs($t->quantity), 3, '.', ''), '0'), '.') : '' }}</td>
                    <td class="text-end">{{ number_format($t->amount, 2) }}</td>
                    <td class="text-end fw-semibold">{{ rtrim(rtrim(number_format($t->balance_quantity, 3, '.', ''), '0'), '.') ?: '0' }}</td><td class="text-end">{{ number_format($t->balance_value, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-body-secondary py-3">{{ __('No movements in this period') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr class="table-light fw-semibold"><td colspan="3">{{ __('Carried forward') }}</td><td class="text-end">{{ rtrim(rtrim(number_format($card['in'], 3, '.', ''), '0'), '.') ?: '0' }}</td><td class="text-end">{{ rtrim(rtrim(number_format($card['out'], 3, '.', ''), '0'), '.') ?: '0' }}</td><td></td><td class="text-end">{{ rtrim(rtrim(number_format($card['closingQuantity'], 3, '.', ''), '0'), '.') ?: '0' }}</td><td class="text-end">{{ number_format($card['closingValue'], 2) }}</td></tr></tfoot>
    </table></div>
</div>
@endsection
