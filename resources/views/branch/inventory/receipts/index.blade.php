@extends('layouts.branch')
@section('title', __('Goods receiving'))

@section('content')
<x-page-header :title="__('Goods receiving')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Goods receiving') => null]">
    <x-slot:actions>@can('inventory.create')<a href="{{ route('branch.inventory.receipts.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-truck me-1"></i>{{ __('Receive goods') }}</a>@endcan</x-slot:actions>
</x-page-header>
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-center">
        <div class="col-md-3"><select name="vendor_id" class="form-select form-select-sm" aria-label="{{ __('Supplier') }}"><option value="">{{ __('All suppliers') }}</option>@foreach($vendors as $id => $name)<option value="{{ $id }}" @selected((int) request('vendor_id') === $id)>{{ $name }}</option>@endforeach</select></div>
        <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Receipt or bill number') }}" aria-label="{{ __('Search') }}"></div>
        <div class="col-auto"><div class="form-check"><input type="checkbox" class="form-check-input" id="f_due" name="due" value="1" @checked(request()->boolean('due'))><label class="form-check-label small" for="f_due">{{ __('Only unpaid') }}</label></div></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    </form></div>
    @if($receipts->isEmpty())<x-empty-state icon="truck" :title="__('No goods receipts')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Bill no.') }}</th><th class="text-end">{{ __('Total (৳)') }}</th><th class="text-end">{{ __('Due (৳)') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>@foreach($receipts as $r)<tr>
            <td><a href="{{ route('branch.inventory.receipts.show', $r) }}" class="fw-semibold text-decoration-none">{{ $r->grn_no }}</a>@if($r->order)<div class="text-body-secondary">{{ $r->order->po_no }}</div>@endif</td>
            <td class="text-nowrap">{{ $r->received_on->format('d M Y') }}</td><td>{{ $r->vendor?->name }}</td><td>{{ $r->bill_no }}</td>
            <td class="text-end">{{ number_format($r->total, 2) }}</td><td class="text-end @if($r->due() > 0) text-danger fw-semibold @endif">{{ $r->due() > 0 ? number_format($r->due(), 2) : '—' }}</td>
            <td><x-inv-status :status="$r->status" />@if($r->status === 'posted')<div class="text-body-secondary">{{ $r->payment_mode === 'paid' ? __('paid') : ($r->due() > 0 ? __('on credit') : __('settled')) }}</div>@endif</td></tr>@endforeach</tbody></table></div>
    <div class="card-body">{{ $receipts->links() }}</div>@endif
</div>
@endsection
