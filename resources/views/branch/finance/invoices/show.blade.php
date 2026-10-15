@extends('layouts.branch')
@section('title', $invoice->invoice_no)

@php($u = auth()->user())

@section('content')
<x-page-header :title="$invoice->invoice_no" :breadcrumbs="[__('Invoices') => route('branch.finance.invoices.index'), $invoice->student?->name => route('branch.finance.students.show', $invoice->student_id), $invoice->invoice_no => null]">
    <x-slot:actions>
        <a href="{{ route('branch.finance.invoices.show', [$invoice, 'print' => 1]) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</a>
        @if($invoice->isOpen() && $u->can('payments.collect'))
            <a href="{{ route('branch.finance.students.show', $invoice->student_id) }}" class="btn btn-success btn-sm"><i class="fa-solid fa-hand-holding-dollar me-1"></i>{{ __('Collect') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body d-flex flex-wrap gap-4 small border-bottom">
                <div><div class="text-body-secondary">{{ __('Student') }}</div><strong>{{ $invoice->student?->name }}</strong> <code>{{ $invoice->student?->student_code }}</code></div>
                <div><div class="text-body-secondary">{{ __('Class') }}</div>{{ $invoice->schoolClass?->name }} {{ $invoice->section?->name }}</div>
                <div><div class="text-body-secondary">{{ __('Issued') }}</div>{{ $invoice->issue_date->format('d M Y') }}</div>
                <div><div class="text-body-secondary">{{ __('Due') }}</div>{{ $invoice->due_date->format('d M Y') }}</div>
                <div><div class="text-body-secondary">{{ __('Status') }}</div><span class="badge text-bg-{{ $invoice->tone() }}">{{ __(ucfirst($invoice->status)) }}</span></div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>{{ __('Particulars') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Net') }}</th></tr></thead>
                    <tbody>
                    @foreach($invoice->items as $item)
                        <tr>
                            <td>{{ $item->description }}@if($item->discount_note)<div class="small text-success">{{ $item->discount_note }}</div>@endif</td>
                            <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                            <td class="text-end">{{ $item->discount > 0 ? number_format($item->discount, 2) : '' }}</td>
                            <td class="text-end">{{ number_format($item->net(), 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" class="text-end">{{ __('Total') }}</th><th class="text-end">{{ number_format($invoice->total, 2) }}</th></tr>
                        <tr><td colspan="3" class="text-end">{{ __('Paid') }}</td><td class="text-end">{{ number_format($invoice->paid, 2) }}</td></tr>
                        @if($invoice->waived > 0)<tr><td colspan="3" class="text-end">{{ __('Waived') }}</td><td class="text-end">{{ number_format($invoice->waived, 2) }}</td></tr>@endif
                        <tr><th colspan="3" class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ number_format($invoice->balance, 2) }}</th></tr>
                    </tfoot>
                </table>
            </div>
            @if($invoice->status === 'cancelled')
                <div class="card-body border-top text-danger small">{{ __('Cancelled on :date: :reason', ['date' => $invoice->cancelled_at?->format('d M Y H:i'), 'reason' => $invoice->cancel_reason]) }}</div>
            @endif
        </div>

        <div class="card mt-3">
            <div class="card-header bg-transparent fw-semibold">{{ __('Payments applied') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($invoice->allocations as $a)
                    <li class="list-group-item d-flex">
                        <span><a href="{{ route('branch.finance.payments.show', $a->fee_payment_id) }}" target="_blank" rel="noopener">{{ $a->payment?->receipt_no }}</a>
                            · {{ $a->payment?->date?->format('d M Y') }} @if($a->from_credit)<span class="badge text-bg-light border">{{ __('from advance') }}</span>@endif</span>
                        <strong class="ms-auto">৳{{ number_format($a->amount, 2) }}</strong>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No payment yet.') }}</li>
                @endforelse
            </ul>
        </div>

        @can('accounting.view')
            <div class="card mt-3">
                <div class="card-header bg-transparent fw-semibold">{{ __('Journal entries') }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach($entries as $e)
                        <li class="list-group-item d-flex gap-2"><code>{{ $e->entry_no }}</code> <span>{{ $e->narration }}</span>
                            <span class="ms-auto">৳{{ number_format($e->amount, 2) }}</span>
                            @if($e->isReversed())<span class="badge text-bg-secondary">{{ __('Reversed') }}</span>@endif</li>
                    @endforeach
                </ul>
            </div>
        @endcan
    </div>

    <div class="col-lg-4">
        @if($invoice->isOpen() && $u->can('invoices.update'))
            <form method="POST" action="{{ route('branch.finance.invoices.waive', $invoice) }}" class="card mb-3" data-confirm="{{ __('Waive this amount? It is recorded as a discount.') }}">
                @csrf
                <div class="card-header bg-transparent fw-semibold">{{ __('Waive (write off)') }}</div>
                <div class="card-body row g-2">
                    <x-form.input name="amount" type="number" step="0.01" min="0.01" :max="$invoice->balance" :label="__('Amount (৳)')" :value="$invoice->balance" required col="col-12" />
                    <x-form.input name="reason" :label="__('Reason')" required col="col-12" minlength="5" maxlength="200" />
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-outline-warning w-100">{{ __('Record waiver') }}</button></div>
            </form>
        @endif
        @if($invoice->status !== 'cancelled' && $u->can('invoices.delete'))
            <form method="POST" action="{{ route('branch.finance.invoices.cancel', $invoice) }}" class="card" data-confirm="{{ __('Cancel this invoice? Its journal entries are reversed; any amount already paid becomes advance credit.') }}">
                @csrf
                <div class="card-header bg-transparent fw-semibold text-danger">{{ __('Cancel invoice') }}</div>
                <div class="card-body"><x-form.input name="reason" :label="__('Reason')" required col="col-12" minlength="5" maxlength="255" /></div>
                <div class="card-footer bg-transparent"><button class="btn btn-outline-danger w-100">{{ __('Cancel invoice') }}</button></div>
            </form>
        @endif
    </div>
</div>
@endsection
