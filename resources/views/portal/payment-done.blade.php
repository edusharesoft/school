@extends('layouts.guest')
@section('title', __('Online payment'))

@php
    [$icon, $tone, $text] = match ($payment->status) {
        'paid' => ['fa-circle-check', 'success', __('Payment received. Thank you! The money receipt is in the app.')],
        'initiated', 'pending' => ['fa-hourglass-half', 'warning', __('We are waiting for the payment provider to confirm this payment. Please check the app again in a few minutes; do not pay twice.')],
        'review' => ['fa-user-clock', 'info', __('The accounts office will check this payment before it is added to your account. You do not need to pay again.')],
        'cancelled' => ['fa-circle-xmark', 'secondary', __('The payment was cancelled. Nothing was charged.')],
        default => ['fa-circle-xmark', 'danger', __('The payment did not go through.')],
    };
@endphp

@section('content')
<div class="text-center">
    <i class="fa-solid {{ $icon }} text-{{ $tone }}" style="font-size: 3rem"></i>
    <h1 class="h4 mt-3">{{ __(ucfirst($payment->status)) }}</h1>
    <p class="text-body-secondary">{{ $text }}</p>
    <p class="small mb-1">{{ __('Payment reference') }}: <span class="font-monospace">{{ $payment->tran_id }}</span></p>
    <p class="small">{{ __('Amount') }}: <strong>৳{{ number_format($payment->paid_amount ?? $payment->amount, 2) }}</strong></p>
    <p class="small text-body-secondary mb-0">{{ __('You can close this page and return to the app.') }}</p>
</div>
@endsection
