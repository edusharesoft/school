@extends('layouts.branch')
@section('title', $payment->tran_id)

@php
    $gatewayName = config("payments.gateways.{$payment->gateway}.short", $payment->gateway);
@endphp

@section('content')
<x-page-header :title="__('Online payment').' '.$payment->tran_id" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Online payments') => route('branch.finance.online-payments.index'), $payment->tran_id => null]">
    <x-slot:actions>
        @if(in_array($payment->status, ['initiated', 'pending', 'failed', 'cancelled', 'expired'], true) && ! config("payments.gateways.{$payment->gateway}.manual"))
            <form method="POST" action="{{ route('branch.finance.online-payments.requery', $payment) }}">@csrf
                <button class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-rotate me-1"></i>{{ __('Check with :gateway', ['gateway' => $gatewayName]) }}</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-transparent d-flex align-items-center">
                <span class="fw-semibold">{{ __('Payment') }}</span>
                <span class="badge text-bg-{{ $payment->tone() }} ms-auto">{{ __(ucfirst($payment->status)) }}</span>
            </div>
            @if($payment->failure_reason)
                <div class="alert alert-{{ $payment->status === 'review' ? 'warning' : 'secondary' }} m-3 mb-0 small">{{ $payment->failure_reason }}</div>
            @endif
            <dl class="row mb-0 p-3 small">
                <dt class="col-sm-4">{{ __('Student') }}</dt>
                <dd class="col-sm-8">@if($payment->student)<a href="{{ route('branch.finance.students.show', $payment->student) }}">{{ $payment->student->name }}</a> <code>{{ $payment->student->student_code }}</code>@endif</dd>
                <dt class="col-sm-4">{{ __('Gateway') }}</dt>
                <dd class="col-sm-8">{{ $gatewayName }}@if($payment->payment_method) · {{ $payment->payment_method }}@endif @if($payment->meta['sandbox'] ?? false)<span class="badge text-bg-warning">{{ __('Sandbox') }}</span>@endif</dd>
                <dt class="col-sm-4">{{ __('Amount asked') }}</dt>
                <dd class="col-sm-8">৳{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</dd>
                <dt class="col-sm-4">{{ __('Amount paid') }}</dt>
                <dd class="col-sm-8">{{ $payment->paid_amount !== null ? '৳'.number_format($payment->paid_amount, 2) : '—' }}</dd>
                <dt class="col-sm-4">{{ __('Gateway reference') }}</dt>
                <dd class="col-sm-8 font-monospace">{{ $payment->gateway_ref ?? '—' }}</dd>
                <dt class="col-sm-4">{{ __('Gateway transaction ID') }}</dt>
                <dd class="col-sm-8 font-monospace">{{ $payment->gateway_txn_id ?? '—' }}</dd>
                <dt class="col-sm-4">{{ __('Paid by') }}</dt>
                <dd class="col-sm-8">{{ $payment->payer?->name ?? '—' }} <span class="text-body-secondary">{{ $payment->payer_ip }}</span></dd>
                <dt class="col-sm-4">{{ __('Started') }}</dt>
                <dd class="col-sm-8">{{ $payment->created_at->format('d M Y H:i:s') }}</dd>
                <dt class="col-sm-4">{{ __('Verified') }}</dt>
                <dd class="col-sm-8">{{ $payment->verified_at?->format('d M Y H:i:s') ?? '—' }}</dd>
                <dt class="col-sm-4">{{ __('Money receipt') }}</dt>
                <dd class="col-sm-8">@if($payment->feePayment)<a href="{{ route('branch.finance.payments.show', $payment->feePayment) }}" target="_blank" rel="noopener">{{ $payment->feePayment->receipt_no }}</a>@if($payment->feePayment->isReversed()) <span class="badge text-bg-secondary">{{ __('Reversed') }}</span>@endif @else — @endif</dd>
                @if($payment->status === 'refunded')
                    <dt class="col-sm-4">{{ __('Refund') }}</dt>
                    <dd class="col-sm-8">৳{{ number_format((float) $payment->refunded_amount, 2) }} <span class="font-monospace">{{ $payment->refund_ref }}</span></dd>
                @endif
            </dl>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold">{{ __('Invoices in this payment') }}</div>
            <ul class="list-group list-group-flush small">
                @forelse($invoices as $inv)
                    <li class="list-group-item d-flex gap-2">
                        <a href="{{ route('branch.finance.invoices.show', $inv) }}">{{ $inv->invoice_no }}</a> {{ $inv->title }}
                        <span class="badge text-bg-{{ $inv->tone() }} ms-auto">{{ __(ucfirst($inv->status)) }}</span>
                        <span>{{ __('Balance') }} ৳{{ number_format($inv->balance, 2) }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">—</li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header bg-transparent fw-semibold">{{ __('Gateway log') }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Type') }}</th><th>{{ __('Result') }}</th><th>{{ __('IP') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach($payment->events->sortBy('id') as $event)
                        <tr>
                            <td class="text-nowrap">{{ $event->created_at?->format('d M H:i:s') }}</td>
                            <td>{{ $event->type }}</td>
                            <td>{{ $event->result }}</td>
                            <td>{{ $event->ip }}</td>
                            <td class="text-end">
                                <details><summary class="small">{{ __('Data') }}</summary>
                                    <pre class="small mb-0 text-start" style="max-width: 420px; white-space: pre-wrap">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        @if($payment->status === 'review' && auth()->user()->can('payments.collect'))
            <form method="POST" action="{{ route('branch.finance.online-payments.review', $payment) }}" class="card mb-3 border-warning">
                @csrf
                <div class="card-header bg-transparent fw-semibold">{{ __('Decide about this payment') }}</div>
                <div class="card-body">
                    @if(config("payments.gateways.{$payment->gateway}.manual"))
                        <p class="small text-body-secondary">{{ __('Find this TrxID in the statement of the account that receives the QR payments. Accepting posts a money receipt for the amount the payer reported; rejecting closes the attempt.') }}</p>
                    @else
                    <p class="small text-body-secondary">{{ __('Check the transaction in the :gateway merchant panel first. Accepting posts a money receipt for the amount the gateway reported; anything above the invoices stays as advance credit.', ['gateway' => $gatewayName]) }}</p>
                    @endif
                    <label class="form-label small" for="review_note">{{ __('Note') }} <span class="text-danger">*</span></label>
                    <input id="review_note" name="note" class="form-control @error('note') is-invalid @enderror" value="{{ old('note') }}" required minlength="5" maxlength="200">
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('decision')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="d-flex gap-2 mt-3">
                        <button name="decision" value="accept" class="btn btn-success btn-sm">{{ __('Accept and post receipt') }}</button>
                        <button name="decision" value="reject" class="btn btn-outline-danger btn-sm">{{ __('Reject') }}</button>
                    </div>
                </div>
            </form>
        @endif

        @if($payment->status === 'paid' && auth()->user()->can('payments.refund'))
            <form method="POST" action="{{ route('branch.finance.online-payments.refund', $payment) }}" class="card mb-3" data-confirm="{{ __('Refund the full amount and reverse the money receipt?') }}">
                @csrf
                <div class="card-header bg-transparent fw-semibold">{{ __('Refund') }}</div>
                <div class="card-body">
                    <p class="small text-body-secondary">
                        @if($canRefundByApi)
                            {{ __('The full amount is refunded through the :gateway API and the money receipt is reversed, so the invoices become due again.', ['gateway' => $gatewayName]) }}
                        @else
                            {{ __(':gateway has no refund API. Refund the payer from its merchant panel first, then enter the refund reference here to reverse the money receipt.', ['gateway' => $gatewayName]) }}
                        @endif
                    </p>
                    <label class="form-label small" for="refund_reason">{{ __('Reason') }} <span class="text-danger">*</span></label>
                    <input id="refund_reason" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" required minlength="5" maxlength="255">
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @unless($canRefundByApi)
                        <label class="form-label small mt-2" for="refund_reference">{{ __('Refund reference') }} <span class="text-danger">*</span></label>
                        <input id="refund_reference" name="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference') }}" required maxlength="120">
                        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endunless
                    <button class="btn btn-outline-danger btn-sm mt-3"><i class="fa-solid fa-rotate-left me-1"></i>{{ __('Refund ৳:amount', ['amount' => number_format((float) $payment->paid_amount, 2)]) }}</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
