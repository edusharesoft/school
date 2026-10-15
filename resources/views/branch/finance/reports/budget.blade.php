@extends('layouts.branch')
@section('title', __('Budget'))

@php($canEdit = auth()->user()->can('accounting.post'))

@section('content')
<x-page-header :title="__('Budget vs actual')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Budget') => null]" />
@include('branch.finance.reports._acc_nav')

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 align-items-end">
            <div><label class="form-label small" for="f_fy">{{ __('Financial year') }}</label>
                <select id="f_fy" name="financial_year_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($years as $y)<option value="{{ $y->id }}" @selected($year->id === $y->id)>{{ $y->name }}</option>@endforeach
                </select></div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('branch.finance.reports.budget.save') }}" class="card">
    @csrf
    <input type="hidden" name="financial_year_id" value="{{ $year->id }}">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>{{ __('Account') }}</th><th style="width:170px" class="text-end">{{ __('Budget (৳)') }}</th><th class="text-end">{{ __('Actual (৳)') }}</th><th class="text-end">{{ __('Remaining (৳)') }}</th><th style="width:200px">{{ __('Used') }}</th></tr></thead>
            <tbody>
            @foreach(['income' => __('Income'), 'expense' => __('Expenditure')] as $type => $label)
                <tr class="table-light"><th colspan="5">{{ $label }}</th></tr>
                @foreach($rows->filter(fn ($r) => $r['account']->type === $type) as $r)
                    <tr>
                        <td>{{ $r['account']->label() }}</td>
                        <td><input type="number" step="0.01" min="0" name="amounts[{{ $r['account']->id }}]" value="{{ $r['budget'] ?: '' }}" class="form-control form-control-sm text-end" @disabled(! $canEdit) aria-label="{{ __('Budget') }}"></td>
                        <td class="text-end">{{ number_format($r['actual'], 2) }}</td>
                        <td class="text-end">{{ $r['budget'] ? number_format($r['variance'], 2) : '' }}</td>
                        <td>@if($r['used'] !== null)
                            <div class="progress" style="height:8px"><div class="progress-bar {{ $type === 'expense' ? ($r['used'] > 100 ? 'bg-danger' : 'bg-warning') : 'bg-success' }}" style="width: {{ min(100, $r['used']) }}%"></div></div>
                            <span class="text-body-secondary">{{ $r['used'] }}%</span>@endif</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
    @if($canEdit)<div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Save budget') }}</button></div>@endif
</form>
@endsection
