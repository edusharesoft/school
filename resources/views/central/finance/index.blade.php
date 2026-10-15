@extends('layouts.central')
@section('title', __('Consolidated finance'))

@section('content')
<x-page-header :title="__('Consolidated finance')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Consolidated finance') => null]" />

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-auto"><button class="btn btn-sm btn-brand">{{ __('Show') }}</button></div>
            <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([
        ['label' => __('Total income'), 'value' => $total['income'], 'tone' => 'success'],
        ['label' => __('Total expenditure'), 'value' => $total['expense'], 'tone' => 'danger'],
        ['label' => __('Surplus / deficit'), 'value' => $total['surplus'], 'tone' => $total['surplus'] >= 0 ? 'primary' : 'danger'],
        ['label' => __('Fees outstanding'), 'value' => $total['outstanding'], 'tone' => 'warning'],
    ] as $card)
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="small text-body-secondary">{{ $card['label'] }}</div>
                <div class="fs-5 fw-semibold text-{{ $card['tone'] }}">৳{{ number_format($card['value'], 2) }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-header bg-transparent fw-semibold">{{ __('Branch-wise summary') }} — {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light">
            <tr>
                <th>{{ __('Branch') }}</th>
                <th class="text-end">{{ __('Income') }}</th>
                <th class="text-end">{{ __('Expenditure') }}</th>
                <th class="text-end">{{ __('Surplus / deficit') }}</th>
                <th class="text-end">{{ __('Fees collected') }}</th>
                <th class="text-end">{{ __('Fees outstanding') }}</th>
                <th class="text-end">{{ __('Cash & bank') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['branch']->name }} <span class="text-body-secondary">({{ $r['branch']->code }})</span></td>
                    <td class="text-end">{{ number_format($r['income'], 2) }}</td>
                    <td class="text-end">{{ number_format($r['expense'], 2) }}</td>
                    <td class="text-end {{ $r['surplus'] < 0 ? 'text-danger' : '' }}">{{ number_format($r['surplus'], 2) }}</td>
                    <td class="text-end">{{ number_format($r['collected'], 2) }}</td>
                    <td class="text-end">{{ number_format($r['outstanding'], 2) }}</td>
                    <td class="text-end">{{ number_format($r['cash'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No branches to show.') }}</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light fw-semibold">
            <tr>
                <td>{{ __('Total') }}</td>
                <td class="text-end">{{ number_format($total['income'], 2) }}</td>
                <td class="text-end">{{ number_format($total['expense'], 2) }}</td>
                <td class="text-end {{ $total['surplus'] < 0 ? 'text-danger' : '' }}">{{ number_format($total['surplus'], 2) }}</td>
                <td class="text-end">{{ number_format($total['collected'], 2) }}</td>
                <td class="text-end">{{ number_format($total['outstanding'], 2) }}</td>
                <td class="text-end">{{ number_format($total['cash'], 2) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

@php
    $incomeLines = $lines->where('type', 'income');
    $expenseLines = $lines->where('type', 'expense');
@endphp
<div class="card" style="max-width: 1000px">
    <div class="card-header bg-transparent fw-semibold">{{ __('Consolidated income & expenditure') }}</div>
    <div class="row g-0">
        <div class="col-md-6 border-end">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Income') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>
                @forelse($incomeLines as $l)<tr><td><span class="text-body-secondary">{{ $l['code'] }}</span> {{ __($l['name']) }}</td><td class="text-end">{{ number_format($l['amount'], 2) }}</td></tr>@empty<tr><td colspan="2" class="text-body-secondary">—</td></tr>@endforelse
                </tbody>
                <tfoot><tr><th>{{ __('Total income') }}</th><th class="text-end">{{ number_format($incomeLines->sum('amount'), 2) }}</th></tr></tfoot>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Expenditure') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>
                @forelse($expenseLines as $l)<tr><td><span class="text-body-secondary">{{ $l['code'] }}</span> {{ __($l['name']) }}</td><td class="text-end">{{ number_format($l['amount'], 2) }}</td></tr>@empty<tr><td colspan="2" class="text-body-secondary">—</td></tr>@endforelse
                </tbody>
                <tfoot><tr><th>{{ __('Total expenditure') }}</th><th class="text-end">{{ number_format($expenseLines->sum('amount'), 2) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
    <div class="card-footer bg-transparent small text-body-secondary">{{ __('Accounts with the same code are added together across branches. Transfers between branches are not eliminated.') }}</div>
</div>
@endsection
