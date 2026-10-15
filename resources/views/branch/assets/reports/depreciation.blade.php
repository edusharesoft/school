@extends('layouts.branch')
@section('title', __('Depreciation report'))

@section('content')
<x-page-header :title="__('Asset reports')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Reports') => route('branch.assets.reports.summary'), __('Depreciation') => null]" />
@include('branch.assets.reports._nav')
<div class="card mb-3"><div class="card-body"><form method="GET" class="row g-2 align-items-end">
    <div class="col-auto"><label class="form-label small mb-0" for="f">{{ __('From month') }}</label><input id="f" type="month" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><label class="form-label small mb-0" for="t">{{ __('To month') }}</label><input id="t" type="month" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    <div class="col text-md-end small text-body-secondary">{{ __('Financial year :y is the default.', ['y' => $year->name]) }} · {{ __('Total charged: ৳:t', ['t' => number_format($total, 2)]) }}</div>
</form></div></div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('By category') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Assets') }}</th><th class="text-end">{{ __('Charged (৳)') }}</th></tr></thead>
            <tbody>@forelse($byCategory as $c)<tr><td>{{ $c['category'] }}</td><td class="text-end">{{ $c['assets'] }}</td><td class="text-end">{{ number_format($c['amount'], 2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('Nothing charged in this period') }}</td></tr>@endforelse</tbody>
            <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end">{{ __('Total') }}</td><td class="text-end">{{ number_format($total, 2) }}</td></tr></tfoot></table></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('By month') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Assets') }}</th><th class="text-end">{{ __('Charged (৳)') }}</th></tr></thead>
            <tbody>@forelse($byMonth as $m)<tr><td>{{ $m['period'] }}</td><td class="text-end">{{ $m['assets'] }}</td><td class="text-end">{{ number_format($m['amount'], 2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-body-secondary py-3">—</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
