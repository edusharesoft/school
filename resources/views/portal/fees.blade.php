@extends('layouts.portal')
@section('title', __('Fees'))

@php
    $gateways = app(\App\Services\Payments\PaymentSettings::class)->enabled();
    $openInvoices = $invoices->filter(fn ($i) => $i->isOpen() && $i->balance > 0)->sortBy('due_date')->values();
    $onlineAttempts = \App\Models\Payments\OnlinePayment::query()->where('student_id', $student->id)->latest('id')->limit(5)->get();
@endphp

@section('content')
<x-page-header :title="__('Fees').' — '.$student->name" :breadcrumbs="[__('Home') => route(rtrim($routePrefix, '.')), __('Fees') => null]" />

<div class="row g-3 mb-3">
    <div class="col-md-4"><x-stat-card :label="__('Total due')" :value="'৳'.number_format($due, 2)" icon="fa-solid fa-file-invoice-dollar" bg="#fee2e2" color="#b91c1c" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Overdue')" :value="'৳'.number_format($overdue, 2)" icon="fa-solid fa-triangle-exclamation" bg="#fef3c7" color="#b45309" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Advance credit')" :value="'৳'.number_format($credit, 2)" icon="fa-solid fa-piggy-bank" bg="#dcfce7" color="#15803d" /></div>
</div>

<div class="card mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('Invoices') }}</div>
    @if($invoices->isEmpty())
        <x-empty-state icon="receipt" :title="__('No invoices yet')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0 small">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Due date') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Balance') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($invoices as $inv)
                    <tr @class(['table-danger' => $inv->isOpen() && $inv->due_date->isPast()])>
                        <td class="fw-semibold">{{ $inv->title }}<div class="fw-normal text-body-secondary">{{ $inv->invoice_no }}</div></td>
                        <td>{{ $inv->due_date->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format($inv->total, 2) }}</td>
                        <td class="text-end">{{ number_format($inv->paid, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($inv->balance, 2) }}</td>
                        <td><span class="badge text-bg-{{ $inv->tone() }}">{{ __(ucfirst($inv->status)) }}</span></td>
                        <td class="text-end"><a href="{{ route($routePrefix.'fees.invoice', $inv) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if($gateways === [])
        <div class="card-footer bg-transparent small text-body-secondary">{{ __('Pay at the school accounts office.') }}</div>
    @endif
</div>

@if($gateways !== [] && $openInvoices->isNotEmpty())
<form method="POST" action="{{ route('payments.start', $student) }}" class="card mb-3" id="online-pay">
    @csrf
    <div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-globe me-1 text-primary"></i>{{ __('Pay online') }}</div>
    <div class="card-body">
        @error('gateway')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
        @error('invoice_ids')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
        <div class="small fw-semibold mb-2">{{ __('1. Choose the invoices to pay') }}</div>
        <div class="list-group mb-3">
            @foreach($openInvoices as $inv)
                <label class="list-group-item d-flex align-items-center gap-2 small">
                    <input class="form-check-input m-0" type="checkbox" name="invoice_ids[]" value="{{ $inv->id }}" data-amount="{{ $inv->balance }}" @checked(in_array($inv->id, (array) old('invoice_ids', $openInvoices->pluck('id')->all())))>
                    <span>{{ $inv->title }} <span class="text-body-secondary">· {{ $inv->invoice_no }} · {{ __('due') }} {{ $inv->due_date->format('d M Y') }}</span></span>
                    <strong class="ms-auto">৳{{ number_format($inv->balance, 2) }}</strong>
                </label>
            @endforeach
        </div>
        <div class="small fw-semibold mb-2">{{ __('2. Choose how to pay') }}</div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($gateways as $key)
                <input type="radio" class="btn-check" name="gateway" id="gw_{{ $key }}" value="{{ $key }}" @checked(old('gateway', $gateways[0]) === $key) required>
                <label class="btn btn-outline-primary" for="gw_{{ $key }}">{{ config("payments.gateways.$key.short") }}</label>
            @endforeach
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>{{ __('Total') }}: <strong class="fs-5" data-pay-total>৳{{ number_format($openInvoices->sum('balance'), 2) }}</strong></div>
            <button class="btn btn-success ms-auto" data-pay-button><i class="fa-solid fa-lock me-1"></i>{{ __('Continue to secure payment') }}</button>
        </div>
        @if(in_array('banglaqr', $gateways, true))
            <div class="form-text mt-2" data-qr-hint>{{ __('Bangla QR: you will see a QR code with the exact amount. Scan it with any bank or mobile banking app, then send the transaction ID.') }}</div>
        @endif
        <div class="form-text mt-2">{{ __('You will be taken to the payment page of the provider. The school records the payment only after the provider confirms it; the receipt then appears below.') }}</div>
    </div>
</form>
<script>
    (function () {
        const form = document.getElementById('online-pay');
        const total = form.querySelector('[data-pay-total]');
        const button = form.querySelector('[data-pay-button]');
        const update = () => {
            let sum = 0;
            form.querySelectorAll('input[name="invoice_ids[]"]:checked').forEach((c) => { sum += parseFloat(c.dataset.amount || '0'); });
            total.textContent = '৳' + sum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            button.disabled = sum <= 0;
        };
        form.addEventListener('change', update);
        form.addEventListener('submit', () => { button.disabled = true; });
        update();
    })();
</script>
@endif

@if($onlineAttempts->isNotEmpty())
<div class="card mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('Online payment attempts') }}</div>
    <ul class="list-group list-group-flush small">
        @foreach($onlineAttempts as $attempt)
            <li class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                <span>{{ $attempt->created_at->format('d M Y H:i') }} · {{ config("payments.gateways.{$attempt->gateway}.short", $attempt->gateway) }} · <span class="font-monospace">{{ $attempt->tran_id }}</span></span>
                <span class="badge text-bg-{{ $attempt->tone() }}">{{ __(ucfirst($attempt->status)) }}</span>
                <strong class="ms-auto">৳{{ number_format($attempt->amount, 2) }}</strong>
                <a href="{{ route('payments.status', $attempt->tran_id) }}" class="btn btn-sm btn-outline-secondary">{{ __('Details') }}</a>
            </li>
        @endforeach
    </ul>
</div>
@endif

<div class="card">
    <div class="card-header bg-transparent fw-semibold">{{ __('Receipts') }}</div>
    <ul class="list-group list-group-flush small">
        @forelse($payments as $p)
            <li class="list-group-item d-flex align-items-center gap-2">
                <span>{{ $p->date->format('d M Y') }} · {{ $p->receipt_no }} · {{ __(ucfirst($p->method)) }}</span>
                <strong class="ms-auto">৳{{ number_format($p->amount, 2) }}</strong>
                <a href="{{ route($routePrefix.'fees.receipt', $p) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a>
            </li>
        @empty
            <li class="list-group-item text-body-secondary">{{ __('No receipts yet.') }}</li>
        @endforelse
    </ul>
</div>
@endsection
