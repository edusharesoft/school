@extends('layouts.branch')
@section('title', __('Balance sheet'))

@section('content')
<x-page-header :title="__('Balance sheet')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Balance sheet') => null]" />
@include('branch.finance.reports._acc_nav')
@include('branch.finance.reports._range', ['mode' => 'asof'])

<div class="card" style="max-width: 1000px">
    <div class="card-header bg-transparent fw-semibold">{{ tenant()->branch()?->name }} — {{ __('as of :date', ['date' => $asOf->format('d M Y')]) }}</div>
    <div class="row g-0">
        <div class="col-md-6 border-end">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Assets') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>@forelse($assets as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">{{ number_format($r['amount'], 2) }}</td></tr>@empty<tr><td colspan="2">—</td></tr>@endforelse</tbody>
                <tfoot><tr><th>{{ __('Total assets') }}</th><th class="text-end">{{ number_format($totalAssets, 2) }}</th></tr></tfoot>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Liabilities') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>@forelse($liabilities as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">{{ number_format($r['amount'], 2) }}</td></tr>@empty<tr><td colspan="2">—</td></tr>@endforelse</tbody>
                <tfoot><tr><th>{{ __('Total liabilities') }}</th><th class="text-end">{{ number_format($totalLiabilities, 2) }}</th></tr></tfoot>
            </table>
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Fund & equity') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>
                @foreach($equity as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">{{ number_format($r['amount'], 2) }}</td></tr>@endforeach
                <tr><td>{{ __('Accumulated surplus / (deficit)') }}</td><td class="text-end">{{ number_format($surplus, 2) }}</td></tr>
                </tbody>
                <tfoot><tr><th>{{ __('Total fund & equity') }}</th><th class="text-end">{{ number_format($totalEquity, 2) }}</th></tr>
                    <tr><th>{{ __('Total liabilities, fund & equity') }}</th><th class="text-end">{{ number_format($totalLiabilities + $totalEquity, 2) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
    <div class="card-footer bg-transparent small {{ $balanced ? 'text-success' : 'text-danger' }}">{{ $balanced ? __('Assets equal liabilities plus fund — the balance sheet balances.') : __('The balance sheet does not balance. Check the trial balance.') }}</div>
</div>
@endsection
