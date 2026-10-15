@extends('layouts.branch')
@section('title', __('Online payments'))

@php
    $gatewayLabels = collect(config('payments.gateways'))->map(fn ($g) => $g['short'])->all();
    $paid = $totals->where('status', 'paid');
@endphp

@section('content')
<x-page-header :title="__('Online payments')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Online payments') => null]">
    <x-slot:actions>
        @if(app(\App\Services\Payments\PaymentSettings::class)->isEnabled('banglaqr') && auth()->user()->can('payments.collect'))
            <a href="{{ route('branch.finance.online-payments.statement') }}" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-file-csv me-1"></i>{{ __('Match a bank statement') }}</a>
        @endif
        @can('settings.update')<a href="{{ route('branch.finance.online-payments.settings') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-plug me-1"></i>{{ __('Gateway settings') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

@if($review > 0)
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>{{ trans_choice(':count payment needs review: the gateway reported it but it was not posted automatically (amount mismatch, duplicate or risk flag).|:count payments need review: the gateway reported them but they were not posted automatically (amount mismatch, duplicate or risk flag).', $review, ['count' => $review]) }}</span>
        <a href="{{ route('branch.finance.online-payments.index', ['status' => 'review', 'from' => now()->subYear()->toDateString()]) }}" class="btn btn-sm btn-warning ms-auto">{{ __('Show') }}</a>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4"><x-stat-card :label="__('Received online')" :value="'৳'.number_format((float) $paid->sum('total'), 2)" icon="fa-solid fa-circle-check" bg="#dcfce7" color="#15803d" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Successful payments')" :value="(int) $paid->sum('n')" icon="fa-solid fa-receipt" bg="#e0f2fe" color="#0369a1" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Failed / cancelled / expired')" :value="(int) $totals->whereIn('status', ['failed', 'cancelled', 'expired'])->sum('n')" icon="fa-solid fa-circle-xmark" bg="#fee2e2" color="#b91c1c" /></div>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><select name="gateway" class="form-select form-select-sm" aria-label="{{ __('Gateway') }}"><option value="">{{ __('All gateways') }}</option>
                @foreach($gatewayLabels as $key => $label)<option value="{{ $key }}" @selected(request('gateway') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>
                @foreach(App\Models\Payments\OnlinePayment::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach</select></div>
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Reference, transaction ID or student') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Filter') }}"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($totals->isNotEmpty())
        <div class="card-body border-bottom small d-flex flex-wrap gap-3">
            @foreach($totals->groupBy('gateway') as $gateway => $rows)
                <span><strong>{{ $gatewayLabels[$gateway] ?? $gateway }}</strong>:
                    @foreach($rows as $row)<span class="badge text-bg-light border">{{ __(ucfirst($row->status)) }} {{ $row->n }}</span> @endforeach
                </span>
            @endforeach
        </div>
    @endif
    @if($payments->isEmpty())
        <x-empty-state icon="globe" :title="__('No online payments in this period')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Started') }}</th><th>{{ __('Student') }}</th><th>{{ __('Gateway') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Receipt') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($payments as $p)
                    <tr>
                        <td class="font-monospace">{{ $p->tran_id }}@if($p->gateway_txn_id)<div class="text-body-secondary">{{ $p->gateway_txn_id }}</div>@endif</td>
                        <td>{{ $p->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $p->student?->name }} <code>{{ $p->student?->student_code }}</code></td>
                        <td>{{ $gatewayLabels[$p->gateway] ?? $p->gateway }}@if($p->payment_method)<div class="text-body-secondary">{{ $p->payment_method }}</div>@endif</td>
                        <td class="text-end fw-semibold">{{ number_format($p->paid_amount ?? $p->amount, 2) }}</td>
                        <td><span class="badge text-bg-{{ $p->tone() }}">{{ __(ucfirst($p->status)) }}</span></td>
                        <td>{{ $p->feePayment?->receipt_no ?? '—' }}</td>
                        <td class="text-end"><a href="{{ route('branch.finance.online-payments.show', $p) }}" class="btn btn-sm btn-outline-secondary">{{ __('Details') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
