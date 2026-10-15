@extends('layouts.branch')
@section('title', __('Ledger'))

@section('content')
<x-page-header :title="__('Ledger').($account ? ' — '.$account->name : '')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Ledger') => null]" />
@include('branch.finance.reports._acc_nav')

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label small" for="f_acc">{{ __('Account') }}</label>
                <select id="f_acc" name="account_id" class="form-select form-select-sm">
                    @foreach($accounts as $a)<option value="{{ $a->id }}" @selected($account?->id === $a->id)>{{ $a->label() }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-auto"><button class="btn btn-sm btn-brand">{{ __('Show') }}</button></div>
            <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button></div>
        </form>
    </div>
</div>

@if($account)
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Particulars') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
            <tbody>
                <tr class="table-light"><td>{{ $from->format('d M Y') }}</td><td></td><td class="fw-semibold">{{ __('Opening balance') }}</td><td></td><td></td><td class="text-end fw-semibold">{{ number_format($opening, 2) }}</td></tr>
                @foreach($rows as $r)
                    <tr>
                        <td class="text-nowrap">{{ $r['line']->date->format('d M Y') }}</td>
                        <td class="text-nowrap"><a href="{{ route('branch.finance.journal.show', $r['line']->journal_entry_id) }}"><code>{{ $r['line']->entry?->entry_no }}</code></a></td>
                        <td>{{ $r['line']->entry?->narration }}@if($r['line']->description) <span class="text-body-secondary">— {{ $r['line']->description }}</span>@endif</td>
                        <td class="text-end">{{ $r['line']->debit > 0 ? number_format($r['line']->debit, 2) : '' }}</td>
                        <td class="text-end">{{ $r['line']->credit > 0 ? number_format($r['line']->credit, 2) : '' }}</td>
                        <td class="text-end">{{ number_format($r['balance'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light"><tr><th colspan="3" class="text-end">{{ __('Closing balance') }}</th><th class="text-end">{{ number_format($debit, 2) }}</th><th class="text-end">{{ number_format($credit, 2) }}</th><th class="text-end">{{ number_format($closing, 2) }}</th></tr></tfoot>
        </table>
    </div>
    <div class="card-footer bg-transparent small text-body-secondary">{{ $account->isDebitNormal() ? __('Positive balance = debit balance.') : __('Positive balance = credit balance.') }}</div>
</div>
@endif
@endsection
