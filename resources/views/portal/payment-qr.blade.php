@extends('layouts.portal')
@section('title', __('Pay with Bangla QR'))

@section('content')
<x-page-header :title="__('Pay with Bangla QR')" :breadcrumbs="[__('Fees') => $feesUrl, $payment->tran_id => null]" />

<div class="row g-3 justify-content-center">
    <div class="col-lg-5">
        <div class="card text-center">
            <div class="card-body">
                <div class="small text-body-secondary">{{ __('Amount to pay') }}</div>
                <div class="display-6 fw-bold mb-2">৳{{ number_format($payment->amount, 2) }}</div>
                <div class="d-inline-block border rounded p-2 bg-white" style="line-height:0" aria-label="{{ __('Bangla QR code for :amount', ['amount' => '৳'.number_format($payment->amount, 2)]) }}">{!! $svg !!}</div>
                <div class="mt-2 fw-semibold">{{ $merchant['name'] }}</div>
                @if($merchant['city'])<div class="small text-body-secondary">{{ $merchant['city'] }}</div>@endif
                <div class="small text-body-secondary mt-1">{{ __('Payment reference') }}: <span class="font-monospace user-select-all">{{ $payment->tran_id }}</span></div>
            </div>
            <ul class="list-group list-group-flush small text-start">
                <li class="list-group-item"><strong>{{ $payment->student?->name }}</strong> <span class="text-body-secondary">· {{ $payment->student?->student_code }}</span></li>
                @foreach($invoices as $inv)
                    <li class="list-group-item d-flex gap-2"><span>{{ $inv->title }} <span class="text-body-secondary">· {{ $inv->invoice_no }}</span></span><strong class="ms-auto">৳{{ number_format($inv->balance, 2) }}</strong></li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-qrcode me-1 text-primary"></i>{{ __('How to pay') }}</div>
            <ol class="small mb-0 py-3 pe-3">
                <li class="mb-1">{{ __('Open any bank app or mobile banking app (bKash, Nagad, Rocket, Upay …) and choose "Scan QR" / "QR Pay".') }}</li>
                <li class="mb-1">{{ __('Scan the code. Check that the amount is ৳:amount and the name is :name, then pay.', ['amount' => number_format($payment->amount, 2), 'name' => $merchant['name']]) }}</li>
                <li class="mb-1">{{ __('Copy the transaction ID (TrxID) from the confirmation message or the app, and send it below.') }}</li>
            </ol>
            @if($instructions !== '')<div class="card-footer bg-transparent small">{{ $instructions }}</div>@endif
        </div>

        <form method="POST" action="{{ route('payments.qr.claim', $payment->tran_id) }}" class="card">
            @csrf
            <div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-paper-plane me-1 text-success"></i>{{ __('I have paid') }}</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label" for="trx_id">{{ __('Transaction ID (TrxID)') }} <span class="text-danger">*</span></label>
                    <input id="trx_id" name="trx_id" value="{{ old('trx_id') }}" class="form-control font-monospace text-uppercase @error('trx_id') is-invalid @enderror" required minlength="6" maxlength="40" autocomplete="off" autocapitalize="characters">
                    @error('trx_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="amount">{{ __('Amount you paid') }}</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="1" value="{{ old('amount', $payment->amount) }}" class="form-control @error('amount') is-invalid @enderror">
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="payer_account">{{ __('Your wallet / account number') }}</label>
                    <input id="payer_account" name="payer_account" value="{{ old('payer_account') }}" class="form-control @error('payer_account') is-invalid @enderror" maxlength="40" autocomplete="off">
                    @error('payer_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>{{ __('Send to the accounts office') }}</button>
                    <div class="form-text mt-2">{{ __('The school adds the receipt after the accounts office has seen the payment in its bank or wallet statement. Pay only once.') }}</div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
