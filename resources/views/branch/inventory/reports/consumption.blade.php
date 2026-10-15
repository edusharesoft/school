@extends('layouts.branch')
@section('title', __('Consumption'))

@section('content')
<x-page-header :title="__('Inventory reports')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Reports') => route('branch.inventory.reports.stock'), __('Consumption') => null]" />
@include('branch.inventory.reports._nav')
<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-auto"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    <div class="col text-md-end small text-body-secondary">{{ __(':n issues · value :v', ['n' => $issues, 'v' => money_bdt($total)]) }}</div>
</form>
<div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('By item') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Value (৳)') }}</th></tr></thead>
            <tbody>@forelse($byItem as $r)<tr><td>{{ $r->name }}</td><td class="text-end">{{ rtrim(rtrim(number_format($r->quantity, 3, '.', ''), '0'), '.') }} {{ $r->unit }}</td><td class="text-end">{{ number_format($r->value, 2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('Nothing was issued in this period') }}</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('By department') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Department') }}</th><th class="text-end">{{ __('Issues') }}</th><th class="text-end">{{ __('Value (৳)') }}</th></tr></thead>
            <tbody>@forelse($byDepartment as $r)<tr><td>{{ $r->department }}</td><td class="text-end">{{ $r->issues }}</td><td class="text-end">{{ number_format($r->value, 2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-body-secondary py-3">—</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
