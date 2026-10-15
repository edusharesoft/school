@extends('layouts.branch')
@section('title', __('Purchases & dues'))

@section('content')
<x-page-header :title="__('Inventory reports')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Reports') => route('branch.inventory.reports.stock'), __('Purchases & dues') => null]" />
@include('branch.inventory.reports._nav')
<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-auto"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    <div class="col text-md-end small text-body-secondary">{{ __(':n receipts · total :v', ['n' => $receipts->count(), 'v' => money_bdt($total)]) }}</div>
</form>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('By supplier') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Supplier') }}</th><th class="text-end">{{ __('Receipts') }}</th><th class="text-end">{{ __('Bought (৳)') }}</th><th class="text-end">{{ __('Unpaid (৳)') }}</th></tr></thead>
            <tbody>@forelse($bySupplier as $r)<tr><td>{{ $r['vendor'] }}</td><td class="text-end">{{ $r['receipts'] }}</td><td class="text-end">{{ number_format($r['total'], 2) }}</td><td class="text-end @if($r['due'] > 0) text-danger fw-semibold @endif">{{ number_format($r['due'], 2) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No goods received in this period') }}</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Receipts') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th><th class="text-end">{{ __('Total (৳)') }}</th></tr></thead>
            <tbody>@forelse($receipts as $r)<tr><td><a href="{{ route('branch.inventory.receipts.show', $r) }}" class="text-decoration-none">{{ $r->grn_no }}</a></td><td class="text-nowrap">{{ $r->received_on->format('d M Y') }}</td><td>{{ $r->vendor?->name }}</td><td class="text-end">{{ number_format($r->total, 2) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-body-secondary py-3">—</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
