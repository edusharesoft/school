@extends('layouts.branch')
@section('title', __('Income & expenditure'))

@section('content')
<x-page-header :title="__('Income & expenditure')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Income & expenditure') => null]" />
@include('branch.finance.reports._acc_nav')
@include('branch.finance.reports._range')

<div class="card" style="max-width: 1000px">
    <div class="card-header bg-transparent fw-semibold">{{ tenant()->branch()?->name }} — {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div>
    <div class="row g-0">
        <div class="col-md-6 border-end">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Income') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>
                @forelse($income as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">{{ number_format($r['amount'], 2) }}</td></tr>@empty<tr><td colspan="2" class="text-body-secondary">—</td></tr>@endforelse
                </tbody>
                <tfoot><tr><th>{{ __('Total income') }}</th><th class="text-end">{{ number_format($totalIncome, 2) }}</th></tr></tfoot>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Expenditure') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>
                @forelse($expense as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">{{ number_format($r['amount'], 2) }}</td></tr>@empty<tr><td colspan="2" class="text-body-secondary">—</td></tr>@endforelse
                </tbody>
                <tfoot><tr><th>{{ __('Total expenditure') }}</th><th class="text-end">{{ number_format($totalExpense, 2) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
    <div class="card-footer bg-transparent fw-semibold {{ $surplus >= 0 ? 'text-success' : 'text-danger' }}">
        {{ $surplus >= 0 ? __('Surplus (income over expenditure)') : __('Deficit (expenditure over income)') }}: ৳{{ number_format(abs($surplus), 2) }}
    </div>
</div>
@endsection
