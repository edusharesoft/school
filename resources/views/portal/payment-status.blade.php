@extends('layouts.portal')
@section('title', __('Online payment'))

@php
    $manual = (bool) config("payments.gateways.{$payment->gateway}.manual");
    $icon = match ($payment->status) {
        'paid' => ['fa-circle-check', 'success'],
        'initiated', 'pending' => ['fa-hourglass-half', 'warning'],
        'review' => ['fa-user-clock', 'info'],
        'refunded' => ['fa-rotate-left', 'info'],
        default => ['fa-circle-xmark', 'danger'],
    };
    $message = match ($payment->status) {
        'paid' => __('Payment received. Thank you! The money receipt is ready.'),
        'initiated', 'pending' => $manual
            ? __('The QR code is ready. After you have paid, send the transaction ID so the accounts office can find your payment.')
            : __('We are waiting for the payment provider to confirm this payment. Please check again in a few minutes; do not pay twice.'),
        'review' => $manual
            ? __('Thank you. The accounts office is checking your payment in the bank / wallet statement; the receipt appears here when it is accepted. You do not need to pay again.')
            : __('The provider reported this payment, but the accounts office must check it before it is added to your account. You do not need to pay again.'),
        'refunded' => __('This payment was refunded.'),
        'cancelled' => __('The payment was cancelled. Nothing was charged.'),
        'expired' => __('The payment page expired before the payment was completed.'),
        default => __('The payment did not go through.'),
    };
@endphp

@section('content')
<x-page-header :title="__('Online payment')" :breadcrumbs="[__('Fees') => $feesUrl, $payment->tran_id => null]" />

<div class="card mx-auto" style="max-width: 640px">
    <div class="card-body text-center py-4">
        <i class="fa-solid {{ $icon[0] }} text-{{ $icon[1] }}" style="font-size: 3rem"></i>
        <h2 class="h4 mt-3 mb-1">{{ __(ucfirst($payment->status)) }}</h2>
        <p class="text-body-secondary mb-0">{{ $message }}</p>
        @if($payment->failure_reason && ! in_array($payment->status, ['paid', 'refunded'], true))
            <p class="small text-body-secondary mt-2 mb-0">{{ $payment->failure_reason }}</p>
        @endif
    </div>
    <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex"><span>{{ __('Student') }}</span><strong class="ms-auto">{{ $payment->student?->name }}</strong></li>
        <li class="list-group-item d-flex"><span>{{ __('Paid with') }}</span><strong class="ms-auto">{{ config("payments.gateways.{$payment->gateway}.short", $payment->gateway) }}@if($payment->payment_method) · {{ $payment->payment_method }}@endif</strong></li>
        <li class="list-group-item d-flex"><span>{{ __('Amount') }}</span><strong class="ms-auto">৳{{ number_format($payment->paid_amount ?? $payment->amount, 2) }}</strong></li>
        <li class="list-group-item d-flex"><span>{{ __('Payment reference') }}</span><span class="ms-auto font-monospace">{{ $payment->tran_id }}</span></li>
        @if($payment->gateway_txn_id)
            <li class="list-group-item d-flex"><span>{{ __('Provider transaction ID') }}</span><span class="ms-auto font-monospace">{{ $payment->gateway_txn_id }}</span></li>
        @endif
        @if($payment->feePayment)
            <li class="list-group-item d-flex"><span>{{ __('Money receipt') }}</span><strong class="ms-auto">{{ $payment->feePayment->receipt_no }}</strong></li>
        @endif
        <li class="list-group-item d-flex"><span>{{ __('Started') }}</span><span class="ms-auto">{{ $payment->created_at->format('d M Y H:i') }}</span></li>
    </ul>
    <div class="card-body d-flex gap-2 justify-content-center flex-wrap">
        @if($receiptUrl)
            <a href="{{ $receiptUrl }}" target="_blank" rel="noopener" class="btn btn-success"><i class="fa-solid fa-print me-1"></i>{{ __('Money receipt') }}</a>
        @endif
        @if($manual && in_array($payment->status, ['initiated', 'pending', 'expired'], true))
            <a href="{{ route('payments.qr', $payment->tran_id) }}" class="btn btn-primary"><i class="fa-solid fa-qrcode me-1"></i>{{ __('Show the QR code') }}</a>
        @elseif($payment->isOpen())
            <a href="{{ route('payments.status', $payment->tran_id) }}" class="btn btn-outline-primary"><i class="fa-solid fa-rotate me-1"></i>{{ __('Check again') }}</a>
        @endif
        <a href="{{ $feesUrl }}" class="btn btn-outline-secondary">{{ __('Back to fees') }}</a>
    </div>
</div>
@endsection
