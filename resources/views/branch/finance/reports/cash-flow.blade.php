@extends('layouts.branch')
@section('title', __('Cash flow'))

@section('content')
<x-page-header :title="__('Cash flow')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Cash flow') => null]" />
@include('branch.finance.reports._acc_nav')
@include('branch.finance.reports._range')

<div class="card" style="max-width: 1000px">
    <div class="card-header bg-transparent fw-semibold">{{ __('Cash, bank and mobile banking') }} — {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div>
    <div class="card-body border-bottom small">{{ __('Opening balance') }}: <strong>৳{{ number_format($opening, 2) }}</strong></div>
    <div class="row g-0">
        <div class="col-md-6 border-end">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Money in') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>@forelse($inflows as $label => $amount)<tr><td>{{ $label }}</td><td class="text-end">{{ number_format($amount, 2) }}</td></tr>@empty<tr><td colspan="2">—</td></tr>@endforelse</tbody>
                <tfoot><tr><th>{{ __('Total in') }}</th><th class="text-end">{{ number_format($totalIn, 2) }}</th></tr></tfoot>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Money out') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>@forelse($outflows as $label => $amount)<tr><td>{{ $label }}</td><td class="text-end">{{ number_format($amount, 2) }}</td></tr>@empty<tr><td colspan="2">—</td></tr>@endforelse</tbody>
                <tfoot><tr><th>{{ __('Total out') }}</th><th class="text-end">{{ number_format($totalOut, 2) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
    <div class="card-body border-top small">
        {{ __('Closing balance') }}: <strong>৳{{ number_format($closing, 2) }}</strong>
        <span class="text-body-secondary ms-2">({{ number_format($opening, 2) }} + {{ number_format($totalIn, 2) }} − {{ number_format($totalOut, 2) }})</span>
    </div>
    <table class="table table-sm mb-0 small">
        <thead class="table-light"><tr><th>{{ __('Account') }}</th><th class="text-end">{{ __('Opening') }}</th><th class="text-end">{{ __('Closing') }}</th></tr></thead>
        <tbody>@foreach($accounts as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">{{ number_format($r['opening'], 2) }}</td><td class="text-end">{{ number_format($r['closing'], 2) }}</td></tr>@endforeach</tbody>
    </table>
</div>
@endsection
