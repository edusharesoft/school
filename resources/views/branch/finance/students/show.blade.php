@extends('layouts.branch')
@section('title', __('Fees').' — '.$student->name)

@php
    $u = auth()->user();
    $en = $student->currentEnrollment;
    $open = $invoices->whereIn('status', ['unpaid', 'partial'])->sortBy('due_date');
    $monthNames = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => __(\Carbon\Carbon::create(2000, $m, 1)->format('M'))]);
    $tabs = ['invoices' => __('Invoices'), 'payments' => __('Receipts & refunds'), 'adjustments' => __('Discounts & extra fees'), 'ledger' => __('Ledger')];
@endphp

@section('content')
<x-page-header :title="$student->name" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Collect fees') => route('branch.finance.students.search'), $student->student_code => null]">
    <x-slot:actions>
        @can('invoices.create')
            <a href="{{ route('branch.finance.invoices.create', ['student_id' => $student->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-receipt me-1"></i>{{ __('New invoice') }}</a>
        @endcan
        @can('view', $student)
            <a href="{{ route('branch.students.show', $student) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person me-1"></i>{{ __('Profile') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

@if(session('print_receipt'))
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle"></i> {{ __('Receipt saved.') }}
        <a href="{{ route('branch.finance.payments.show', session('print_receipt')) }}" target="_blank" rel="noopener" class="btn btn-sm btn-success ms-auto"><i class="bi bi-printer me-1"></i>{{ __('Print receipt') }}</a>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body small">
            <div class="fw-semibold fs-6">{{ $student->name }}</div>
            <code>{{ $student->student_code }}</code>
            <div class="mt-1">{{ $en?->schoolClass?->name }} · {{ __('Section') }} {{ $en?->section?->name }} · {{ __('Roll') }} {{ $en?->roll_no ?? '—' }}</div>
            <div class="text-body-secondary">{{ $en?->session?->name }}</div>
            @if($g = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first())
                <div class="mt-1"><i class="bi bi-telephone"></i> {{ $g->name }} {{ $g->phone }}</div>
            @endif
        </div></div>
    </div>
    <div class="col-6 col-md-4"><x-stat-card :label="__('Outstanding')" :value="'৳'.number_format($outstanding, 2)" icon="fa-solid fa-file-invoice-dollar" bg="#fee2e2" color="#b91c1c" :sub="trans_choice(':count open invoice|:count open invoices', $open->count(), ['count' => $open->count()])" /></div>
    <div class="col-6 col-md-4"><x-stat-card :label="__('Advance credit')" :value="'৳'.number_format($credit, 2)" icon="fa-solid fa-piggy-bank" bg="#dcfce7" color="#15803d" :sub="__('used by the next invoices')" /></div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header bg-transparent">
                <ul class="nav nav-tabs card-header-tabs">
                    @foreach($tabs as $key => $label)
                        @if($key !== 'ledger' || $ledger->isNotEmpty() || $u->can('accounting.view'))
                            <li class="nav-item"><a @class(['nav-link', 'active' => $tab === $key]) href="{{ route('branch.finance.students.show', [$student, 'tab' => $key]) }}">{{ $label }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </div>

            @if($tab === 'invoices')
                @if($invoices->isEmpty())
                    <x-empty-state icon="receipt" :title="__('No invoices yet')" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Balance') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                            @foreach($invoices as $inv)
                                <tr @class(['table-danger' => $inv->isOpen() && $inv->due_date->isPast()])>
                                    <td><a href="{{ route('branch.finance.invoices.show', $inv) }}" class="fw-semibold text-decoration-none">{{ $inv->invoice_no }}</a><div class="text-body-secondary">{{ $inv->title }}</div></td>
                                    <td class="text-nowrap">{{ $inv->due_date->format('d M Y') }}</td>
                                    <td class="text-end">{{ number_format($inv->total, 2) }}</td>
                                    <td class="text-end">{{ number_format($inv->paid, 2) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($inv->balance, 2) }}</td>
                                    <td><span class="badge text-bg-{{ $inv->tone() }}">{{ __(ucfirst($inv->status)) }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($tab === 'payments')
                @if($payments->isEmpty() && $refunds->isEmpty())
                    <x-empty-state icon="cash-coin" :title="__('No receipts yet')" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Advance left') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach($payments as $p)
                                <tr @class(['text-decoration-line-through text-body-tertiary' => $p->isReversed()])>
                                    <td class="fw-semibold">{{ $p->receipt_no }}<div class="fw-normal text-body-secondary">{{ $p->receiver?->name }}</div></td>
                                    <td>{{ $p->date->format('d M Y') }}</td>
                                    <td>{{ __(ucfirst($p->method)) }}@if($p->reference)<div class="text-body-secondary">{{ $p->reference }}</div>@endif</td>
                                    <td class="text-end">{{ number_format($p->amount, 2) }}</td>
                                    <td class="text-end">{{ $p->unallocated > 0 ? number_format($p->unallocated, 2) : '—' }}</td>
                                    <td><span class="badge text-bg-{{ $p->isReversed() ? 'secondary' : 'success' }}">{{ __(ucfirst($p->status)) }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('branch.finance.payments.show', $p) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Print receipt') }}"><i class="bi bi-printer"></i></a>
                                        @if(! $p->isReversed() && $u->can('payments.refund'))
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rev{{ $p->id }}" aria-label="{{ __('Reverse') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                                        @endif
                                    </td>
                                </tr>
                                @if(! $p->isReversed() && $u->can('payments.refund'))
                                    <tr class="collapse" id="rev{{ $p->id }}"><td colspan="7">
                                        <form method="POST" action="{{ route('branch.finance.payments.reverse', $p) }}" class="d-flex gap-2" data-confirm="{{ __('Reverse receipt :no? The invoices it paid will be due again.', ['no' => $p->receipt_no]) }}">
                                            @csrf
                                            <input name="reason" class="form-control form-control-sm" required minlength="5" maxlength="255" placeholder="{{ __('Reason (e.g. cheque bounced, entered twice)') }}" aria-label="{{ __('Reason') }}">
                                            <button class="btn btn-sm btn-danger text-nowrap">{{ __('Reverse receipt') }}</button>
                                        </form>
                                    </td></tr>
                                @endif
                            @endforeach
                            @foreach($refunds as $r)
                                <tr class="table-warning">
                                    <td class="fw-semibold"><a href="{{ route('branch.finance.refunds.show', $r) }}" target="_blank" rel="noopener">{{ $r->refund_no }}</a><div class="fw-normal">{{ $r->reason }}</div></td>
                                    <td>{{ $r->date->format('d M Y') }}</td><td>{{ __('Refund') }} · {{ __(ucfirst($r->method)) }}</td>
                                    <td class="text-end">−{{ number_format($r->amount, 2) }}</td><td></td><td></td><td></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($tab === 'adjustments')
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Fee head') }}</th><th>{{ __('Value') }}</th><th>{{ __('Months') }}</th><th>{{ __('Session') }}</th><th>{{ __('Reason') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse($adjustments as $a)
                            <tr @class(['text-body-tertiary' => ! $a->is_active])>
                                <td><span class="badge {{ $a->isDiscount() ? 'text-bg-success' : 'text-bg-primary' }}">{{ __(ucfirst(str_replace('_', ' ', $a->type))) }}</span></td>
                                <td>{{ $a->head?->name ?? __('All heads') }}</td>
                                <td>{{ $a->mode === 'percent' ? (float) $a->value.'%' : '৳'.number_format($a->value, 2) }}</td>
                                <td>{{ $a->months ? collect($a->months)->map(fn ($m) => $monthNames[$m] ?? $m)->implode(', ') : __('Every month') }}</td>
                                <td>{{ $a->session?->name }}</td>
                                <td>{{ $a->reason }}<div class="text-body-secondary">{{ $a->approver?->name }}</div></td>
                                <td class="text-end">
                                    @can('fees.update')
                                        <form method="POST" action="{{ route('branch.finance.students.adjustments.toggle', [$student, $a]) }}">@csrf
                                            <button class="btn btn-sm btn-outline-secondary">{{ $a->is_active ? __('Stop') : __('Resume') }}</button></form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-body-secondary">{{ __('No discounts or extra fees.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @can('fees.update')
                    <form method="POST" action="{{ route('branch.finance.students.adjustments.store', $student) }}" class="card-body border-top row g-2 align-items-end">
                        @csrf
                        <x-form.select name="type" :label="__('Type')" col="col-md-3" required :value="old('type', 'scholarship')"
                            :options="collect(App\Models\Finance\StudentFeeAdjustment::TYPES)->mapWithKeys(fn ($t) => [$t => __(ucfirst(str_replace('_', ' ', $t)))])->all()" />
                        <x-form.select name="fee_head_id" :label="__('Fee head')" col="col-md-3" :options="$heads->all()" :placeholder="__('All heads')" />
                        <x-form.select name="mode" :label="__('Mode')" col="col-md-2" required :options="['percent' => '%', 'fixed' => '৳']" />
                        <x-form.input name="value" type="number" step="0.01" min="0" :label="__('Value')" col="col-md-2" required />
                        <x-form.select name="academic_session_id" :label="__('Session')" col="col-md-2" required :value="$en?->academic_session_id" :options="$sessions->all()" />
                        <div class="col-12 small">
                            <span class="me-2 text-body-secondary">{{ __('Months (empty = every month; required for extra fees)') }}:</span>
                            @foreach($monthNames as $m => $label)<label class="me-2"><input type="checkbox" class="form-check-input me-1" name="months[]" value="{{ $m }}">{{ $label }}</label>@endforeach
                            @error('months')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <x-form.input name="reason" :label="__('Reason / reference')" col="col-md-9" required maxlength="255" />
                        <div class="col-md-3"><button class="btn btn-outline-primary w-100">{{ __('Add') }}</button></div>
                    </form>
                @endcan
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0 small">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Particulars') }}</th><th class="text-end">{{ __('Charged') }}</th><th class="text-end">{{ __('Paid / credited') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
                        <tbody>
                        @forelse($ledger as $row)
                            <tr>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                <td class="text-nowrap"><code>{{ $row['entry_no'] }}</code></td>
                                <td>{{ $row['narration'] }}</td>
                                <td class="text-end">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
                                <td class="text-end">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
                                <td class="text-end fw-semibold">{{ number_format(abs($row['balance']), 2) }} {{ $row['balance'] < 0 ? __('adv.') : ($row['balance'] > 0 ? __('due') : '') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-body-secondary">{{ __('No postings yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-xl-4">
        @can('payments.collect')
            <form method="POST" action="{{ route('branch.finance.payments.store', $student) }}" class="card mb-3" id="collect-form">
                @csrf
                <div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-hand-holding-dollar me-1 text-success"></i> {{ __('Receive payment') }}</div>
                <div class="card-body row g-2">
                    @if($open->isNotEmpty())
                        <div class="col-12 small">
                            <div class="text-body-secondary mb-1">{{ __('Settle (oldest first when none is ticked)') }}</div>
                            @foreach($open as $inv)
                                <label class="d-flex align-items-center gap-2 border-bottom py-1">
                                    <input type="checkbox" class="form-check-input" name="invoice_ids[]" value="{{ $inv->id }}" data-balance="{{ $inv->balance }}">
                                    <span class="me-auto">{{ $inv->title }}<br><span class="text-body-secondary">{{ $inv->invoice_no }} · {{ __('due') }} {{ $inv->due_date->format('d M') }}</span></span>
                                    <strong>৳{{ number_format($inv->balance, 2) }}</strong>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-form.input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount (৳)')" :value="$outstanding > 0 ? $outstanding : null" required col="col-6" />
                    <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-6" max="{{ now()->toDateString() }}" />
                    <x-form.select name="method" :label="__('Method')" col="col-6" required :value="'cash'"
                        :options="collect(App\Models\Finance\FeePayment::METHODS)->mapWithKeys(fn ($m) => [$m => __(ucfirst($m))])->all()" />
                    <x-form.select name="account_id" :label="__('Into account')" col="col-6" :options="$moneyAccounts->all()" :placeholder="__('Default for the method')" />
                    <x-form.input name="reference" :label="__('Reference (cheque / transaction no.)')" col="col-12" maxlength="100" />
                    <x-form.input name="notes" :label="__('Note')" col="col-12" maxlength="255" />
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-success w-100"><i class="bi bi-check2-circle me-1"></i>{{ __('Save & print receipt') }}</button>
                    <div class="small text-body-secondary mt-1">{{ __('Any amount above the selected dues is kept as advance credit.') }}</div></div>
            </form>
        @endcan

        @if($credit > 0 && $u->can('payments.refund'))
            <form method="POST" action="{{ route('branch.finance.refunds.store', $student) }}" class="card" data-confirm="{{ __('Pay this refund out of the advance credit?') }}">
                @csrf
                <div class="card-header bg-transparent fw-semibold">{{ __('Refund advance credit') }}</div>
                <div class="card-body row g-2">
                    <x-form.input name="amount" type="number" step="0.01" min="0.01" :max="$credit" :label="__('Amount (৳)')" :value="$credit" required col="col-6" />
                    <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-6" />
                    <x-form.select name="method" :label="__('Paid by')" col="col-6" required :value="'cash'"
                        :options="collect(App\Models\Finance\FeePayment::METHODS)->mapWithKeys(fn ($m) => [$m => __(ucfirst($m))])->all()" />
                    <x-form.select name="account_id" :label="__('From account')" col="col-6" :options="$moneyAccounts->all()" :placeholder="__('Default for the method')" />
                    <x-form.input name="reason" :label="__('Reason')" col="col-12" required maxlength="255" />
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-outline-danger w-100">{{ __('Record refund') }}</button></div>
            </form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('collect-form');
    if (!form) return;
    const amount = form.querySelector('input[name=amount]');
    form.querySelectorAll('input[name="invoice_ids[]"]').forEach((cb) => cb.addEventListener('change', () => {
        const ticked = [...form.querySelectorAll('input[name="invoice_ids[]"]:checked')];
        if (ticked.length) amount.value = ticked.reduce((s, c) => s + parseFloat(c.dataset.balance || 0), 0).toFixed(2);
    }));
});
</script>
@endpush
