@extends('layouts.branch')
@section('title', $receipt->grn_no)

@section('content')
<x-page-header :title="$receipt->grn_no" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Goods receiving') => route('branch.inventory.receipts.index'), $receipt->grn_no => null]" />
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Unit cost (৳)') }}</th><th class="text-end">{{ __('Amount (৳)') }}</th></tr></thead>
            <tbody>@foreach($receipt->items as $l)<tr><td>{{ $l->item?->name }}<div class="text-body-secondary">{{ $l->item?->code }}</div></td><td class="text-end">{{ rtrim(rtrim(number_format($l->quantity, 3, '.', ''), '0'), '.') }} {{ $l->item?->unit }}</td><td class="text-end">{{ number_format($l->unit_cost, 2) }}</td><td class="text-end">{{ number_format($l->amount, 2) }}</td></tr>@endforeach</tbody>
            <tfoot><tr class="fw-semibold"><td colspan="3" class="text-end">{{ __('Total') }}</td><td class="text-end">{{ number_format($receipt->total, 2) }}</td></tr></tfoot></table></div></div>

        @if($receipt->payment_mode === 'credit')
        <div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Payments to the supplier') }}</div>
            @if($receipt->payments->isEmpty())<div class="card-body small text-body-secondary">{{ __('Nothing paid yet.') }}</div>
            @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
                <thead><tr><th>{{ __('Payment') }}</th><th>{{ __('Date') }}</th><th>{{ __('Paid from') }}</th><th class="text-end">{{ __('Amount (৳)') }}</th><th></th></tr></thead>
                <tbody>@foreach($receipt->payments as $p)<tr @class(['text-decoration-line-through text-body-tertiary' => $p->status === 'reversed'])>
                    <td>{{ $p->payment_no }}<div class="text-body-secondary">{{ $p->reference }}</div></td><td>{{ $p->date->format('d M Y') }}</td><td>{{ $p->account?->name }}</td><td class="text-end">{{ number_format($p->amount, 2) }}</td>
                    <td class="text-end">@if($p->status === 'posted')@can('inventory.approve')<form method="POST" action="{{ route('branch.inventory.payments.reverse', $p) }}" class="d-flex gap-1" data-confirm="{{ __('Reverse this payment?') }}">@csrf<input name="reason" class="form-control form-control-sm" placeholder="{{ __('Reason') }}" required maxlength="200" aria-label="{{ __('Reason') }}"><button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Reverse') }}"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endcan @endif</td></tr>@endforeach</tbody></table></div>@endif
            @if($receipt->due() > 0)@can('inventory.approve')
            <form method="POST" action="{{ route('branch.inventory.receipts.pay', $receipt) }}" class="card-body border-top row g-2 align-items-end">@csrf
                <x-form.input name="amount" type="number" step="0.01" min="0.01" :max="$receipt->due()" :label="__('Amount (৳)')" :value="$receipt->due()" required col="col-md-3" />
                <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-md-3" />
                <div class="col-md-3"><label class="form-label" for="account_id">{{ __('Paid from') }}</label><select id="account_id" name="account_id" class="form-select" required>@foreach($moneyAccounts as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                <x-form.input name="reference" :label="__('Cheque / transaction no.')" maxlength="100" col="col-md-3" />
                <div class="col-12"><button class="btn btn-brand btn-sm"><i class="bi bi-cash-coin me-1"></i>{{ __('Pay supplier') }}</button></div>
            </form>@endcan @endif
        </div>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small">
            <div class="mb-2"><x-inv-status :status="$receipt->status" /> @if($receipt->status === 'posted')<span class="badge text-bg-light border">{{ $receipt->payment_mode === 'paid' ? __('Paid now') : __('On credit') }}</span>@endif</div>
            <dl class="row mb-0">
                <dt class="col-5">{{ __('Supplier') }}</dt><dd class="col-7">{{ $receipt->vendor?->name }}</dd>
                <dt class="col-5">{{ __('Received on') }}</dt><dd class="col-7">{{ $receipt->received_on->format('d M Y') }}</dd>
                <dt class="col-5">{{ __('Bill no.') }}</dt><dd class="col-7">{{ $receipt->bill_no ?: '—' }}</dd>
                @if($receipt->order)<dt class="col-5">{{ __('Order') }}</dt><dd class="col-7"><a href="{{ route('branch.inventory.orders.show', $receipt->order) }}">{{ $receipt->order->po_no }}</a></dd>@endif
                <dt class="col-5">{{ __('Received by') }}</dt><dd class="col-7">{{ $receipt->receiver?->name }}</dd>
                <dt class="col-5">{{ __('Total') }}</dt><dd class="col-7 fw-semibold">{{ money_bdt($receipt->total) }}</dd>
                @if($receipt->payment_mode === 'credit')<dt class="col-5">{{ __('Paid') }}</dt><dd class="col-7">{{ money_bdt($receipt->paid_amount) }}</dd><dt class="col-5">{{ __('Due') }}</dt><dd class="col-7 fw-semibold @if($receipt->due() > 0) text-danger @endif">{{ money_bdt($receipt->due()) }}</dd>@else<dt class="col-5">{{ __('Paid from') }}</dt><dd class="col-7">{{ $receipt->paidFrom?->name }}</dd>@endif
                @if($receipt->note)<dt class="col-5">{{ __('Note') }}</dt><dd class="col-7">{{ $receipt->note }}</dd>@endif
            </dl>
        </div></div>
        @if($receipt->status === 'posted')@can('inventory.approve')
        <form method="POST" action="{{ route('branch.inventory.receipts.reverse', $receipt) }}" class="card" data-confirm="{{ __('Reverse this receipt? The goods leave stock again.') }}">@csrf
            <div class="card-body"><label class="form-label small" for="rv">{{ __('Reverse this receipt') }}</label><input id="rv" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason') }}" required maxlength="200"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reverse') }}</button>
            <div class="form-text">{{ __('Only while the goods are still in stock and no payment is attached.') }}</div></div></form>
        @endcan @endif
    </div>
</div>
@endsection
