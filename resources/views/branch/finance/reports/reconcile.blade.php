@extends('layouts.branch')
@section('title', __('Bank reconciliation'))

@section('content')
<x-page-header :title="__('Bank reconciliation')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Bank reconciliation') => null]" />
@include('branch.finance.reports._acc_nav')

@if($banks->isEmpty())
    <div class="card"><x-empty-state icon="bank" :title="__('No bank or mobile-banking account')" /></div>
@else
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label small" for="f_acc">{{ __('Account') }}</label>
                <select id="f_acc" name="account_id" class="form-select form-select-sm">@foreach($banks as $b)<option value="{{ $b->id }}" @selected($account?->id === $b->id)>{{ $b->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small" for="f_asof">{{ __('Statement date') }}</label><input id="f_asof" type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small" for="f_st">{{ __('Statement closing balance (৳)') }}</label><input id="f_st" type="number" step="0.01" name="statement" value="{{ $statement }}" class="form-control form-control-sm"></div>
            <div class="col-auto"><button class="btn btn-sm btn-brand">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><x-stat-card :label="__('Balance in the books')" :value="'৳'.number_format($book, 2)" icon="fa-solid fa-book" bg="#e0e7ff" color="#4338ca" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Cleared balance')" :value="'৳'.number_format($cleared, 2)" icon="fa-solid fa-check-double" bg="#dcfce7" color="#15803d" :sub="__('should equal the bank statement')" /></div>
    <div class="col-md-4">
        @php($diff = $statement !== null && $statement !== '' ? round((float) $statement - $cleared, 2) : null)
        <x-stat-card :label="__('Difference')" :value="$diff === null ? '—' : '৳'.number_format($diff, 2)" icon="fa-solid fa-scale-unbalanced" :bg="$diff === null || $diff == 0 ? '#e2e8f0' : '#fee2e2'" :color="$diff === null || $diff == 0 ? '#475569' : '#b91c1c'" />
    </div>
</div>

<form method="POST" action="{{ route('branch.finance.reports.reconcile.save') }}" class="card">
    @csrf
    <input type="hidden" name="account_id" value="{{ $account?->id }}">
    <input type="hidden" name="as_of" value="{{ $asOf->toDateString() }}">
    <input type="hidden" name="statement" value="{{ $statement }}">
    <div class="card-header bg-transparent fw-semibold">{{ __('Uncleared entries — tick those shown on the statement') }}</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead><tr><th style="width:40px"></th><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Particulars') }}</th><th class="text-end">{{ __('Deposit') }}</th><th class="text-end">{{ __('Withdrawal') }}</th></tr></thead>
            <tbody>
            @forelse($lines as $l)
                <tr><td><input type="checkbox" class="form-check-input" name="line_ids[]" value="{{ $l->id }}" aria-label="{{ __('Cleared') }}"></td>
                    <td>{{ $l->date->format('d M Y') }}</td><td><code>{{ $l->entry?->entry_no }}</code></td><td>{{ $l->entry?->narration }}</td>
                    <td class="text-end">{{ $l->debit > 0 ? number_format($l->debit, 2) : '' }}</td><td class="text-end">{{ $l->credit > 0 ? number_format($l->credit, 2) : '' }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-body-secondary">{{ __('Everything up to this date is reconciled.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($lines->isNotEmpty() && auth()->user()->can('accounting.post'))<div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Mark ticked entries as cleared') }}</button></div>@endif
</form>
@endif
@endsection
