@extends('layouts.branch')
@section('title', $expense->expense_no)

@php($u = auth()->user())

@section('content')
<x-page-header :title="$expense->expense_no" :breadcrumbs="[__('Expenses') => route('branch.finance.expenses.index'), $expense->expense_no => null]" />

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3"><span class="badge fs-6 text-bg-{{ $expense->tone() }}">{{ __(ucfirst($expense->status)) }}</span>
                    <span class="ms-auto fs-4 fw-bold">৳{{ number_format($expense->amount, 2) }}</span></div>
                <dl class="row small mb-0">
                    <dt class="col-sm-4">{{ __('Date') }}</dt><dd class="col-sm-8">{{ $expense->date->format('d M Y') }}</dd>
                    <dt class="col-sm-4">{{ __('Expense account') }}</dt><dd class="col-sm-8">{{ $expense->account?->label() }}</dd>
                    <dt class="col-sm-4">{{ __('Description') }}</dt><dd class="col-sm-8">{{ $expense->description }}</dd>
                    <dt class="col-sm-4">{{ __('Supplier / payee') }}</dt><dd class="col-sm-8">{{ $expense->vendor?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Payment') }}</dt><dd class="col-sm-8">{{ $expense->payment_mode === 'paid' ? __('Paid now') : __('On credit (pay later)') }}@if($expense->paidFrom) — {{ $expense->paidFrom->label() }}@endif @if($expense->paid_on) · {{ $expense->paid_on->format('d M Y') }}@endif</dd>
                    <dt class="col-sm-4">{{ __('Reference') }}</dt><dd class="col-sm-8">{{ $expense->reference ?? '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Requested by') }}</dt><dd class="col-sm-8">{{ $expense->requester?->name }} · {{ $expense->created_at->format('d M Y H:i') }}</dd>
                    @if($expense->approver)<dt class="col-sm-4">{{ __('Reviewed by') }}</dt><dd class="col-sm-8">{{ $expense->approver->name }} · {{ $expense->approved_at?->format('d M Y H:i') }}@if($expense->review_note)<div>{{ $expense->review_note }}</div>@endif</dd>@endif
                    @if($expense->attachment_path)<dt class="col-sm-4">{{ __('Bill') }}</dt><dd class="col-sm-8"><a href="{{ route('branch.finance.expenses.attachment', $expense) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> {{ __('Open attachment') }}</a></dd>@endif
                </dl>
            </div>
        </div>
        @if($entries->isNotEmpty())
            <div class="card mt-3">
                <div class="card-header bg-transparent fw-semibold">{{ __('Journal entries') }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach($entries as $e)
                        <li class="list-group-item d-flex gap-2"><a href="{{ route('branch.finance.journal.show', $e) }}"><code>{{ $e->entry_no }}</code></a> {{ $e->narration }}
                            <span class="ms-auto">৳{{ number_format($e->amount, 2) }}</span>@if($e->isReversed())<span class="badge text-bg-secondary">{{ __('Reversed') }}</span>@endif</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <div class="col-lg-5">
        @if($expense->status === 'pending' && $u->can('expenses.approve'))
            @if($expense->requested_by === $u->id)
                <div class="alert alert-info small">{{ __('Another authorised person must approve your own expense.') }}</div>
            @else
                <form method="POST" action="{{ route('branch.finance.expenses.approve', $expense) }}" class="card mb-3" data-confirm="{{ __('Approve and post this expense?') }}">
                    @csrf
                    <div class="card-body"><x-form.input name="note" :label="__('Note (optional)')" col="col-12" maxlength="255" /></div>
                    <div class="card-footer bg-transparent"><button class="btn btn-success w-100"><i class="bi bi-check2 me-1"></i>{{ __('Approve') }}</button></div>
                </form>
                <form method="POST" action="{{ route('branch.finance.expenses.reject', $expense) }}" class="card mb-3">
                    @csrf
                    <div class="card-body"><x-form.input name="note" :label="__('Reason for rejection')" required col="col-12" maxlength="255" /></div>
                    <div class="card-footer bg-transparent"><button class="btn btn-outline-danger w-100">{{ __('Reject') }}</button></div>
                </form>
            @endif
        @endif
        @if($expense->status === 'approved' && $u->can('expenses.update'))
            <form method="POST" action="{{ route('branch.finance.expenses.pay', $expense) }}" class="card mb-3" data-confirm="{{ __('Record the payment of this bill?') }}">
                @csrf
                <div class="card-header bg-transparent fw-semibold">{{ __('Pay the supplier') }}</div>
                <div class="card-body row g-2">
                    <x-form.select name="account_id" :label="__('Paid from')" :options="$moneyAccounts->all()" required col="col-12" />
                    <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-12" />
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-brand w-100">{{ __('Record payment') }}</button></div>
            </form>
        @endif
        @if(in_array($expense->status, ['approved', 'paid'], true) && $u->can('accounting.reverse'))
            <form method="POST" action="{{ route('branch.finance.expenses.reverse', $expense) }}" class="card" data-confirm="{{ __('Reverse this expense? Mirror entries are posted.') }}">
                @csrf
                <div class="card-body"><x-form.input name="reason" :label="__('Reason for reversal')" required col="col-12" minlength="5" maxlength="255" /></div>
                <div class="card-footer bg-transparent"><button class="btn btn-outline-danger w-100">{{ __('Reverse expense') }}</button></div>
            </form>
        @endif
    </div>
</div>
@endsection
