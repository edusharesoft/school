@extends('layouts.branch')
@section('title', __('Salary sheet').' — '.$run->monthLabel())

@php
    $u = auth()->user();
@endphp

@section('content')
<x-page-header :title="__('Salary sheet').' — '.$run->monthLabel()" :breadcrumbs="[__('Payroll') => route('branch.finance.payroll.index'), $run->monthLabel() => null]">
    <x-slot:actions>
        <a href="{{ route('branch.finance.payroll.slips', $run) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>{{ __('Salary slips') }}</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print sheet') }}</button>
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><x-stat-card :label="__('Status')" :value="__(ucfirst($run->status))" icon="fa-solid fa-flag" bg="#e0e7ff" color="#4338ca" /></div>
    <div class="col-6 col-md-3"><x-stat-card :label="__('Gross')" :value="'৳'.number_format($run->gross, 2)" icon="fa-solid fa-coins" bg="#dcfce7" color="#15803d" :sub="trans_choice(':count employee|:count employees', $run->employees, ['count' => $run->employees])" /></div>
    <div class="col-6 col-md-3"><x-stat-card :label="__('Deductions')" :value="'৳'.number_format($run->deductions, 2)" icon="fa-solid fa-scissors" bg="#fef3c7" color="#b45309" /></div>
    <div class="col-6 col-md-3"><x-stat-card :label="__('Net pay')" :value="'৳'.number_format($run->net, 2)" icon="fa-solid fa-money-check-dollar" bg="#fee2e2" color="#b91c1c" /></div>
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light"><tr><th>{{ __('Employee') }}</th><th class="text-end">{{ __('Basic') }}</th><th>{{ __('Allowances') }}</th><th class="text-end">{{ __('Gross') }}</th><th>{{ __('Deductions') }}</th><th class="text-end">{{ __('Net') }}</th><th class="no-print"></th></tr></thead>
            <tbody>
            @foreach($run->items->sortBy(fn ($i) => $i->employee?->employee_code) as $item)
                <tr>
                    <td><div class="fw-semibold">{{ $item->employee?->name }}</div><div class="text-body-secondary">{{ $item->employee?->employee_code }} · {{ $item->employee?->designation?->name }}</div>@if($item->remarks)<div class="text-body-secondary fst-italic">{{ $item->remarks }}</div>@endif</td>
                    <td class="text-end">{{ number_format($item->basic, 2) }}</td>
                    <td>@foreach($item->allowances ?? [] as $a)<div>{{ $a['name'] }}: {{ number_format($a['amount'], 2) }}</div>@endforeach</td>
                    <td class="text-end">{{ number_format($item->gross, 2) }}</td>
                    <td>@foreach($item->deductions ?? [] as $d)<div>{{ $d['name'] }}: {{ number_format($d['amount'], 2) }}</div>@endforeach
                        @if($item->unpaid_days > 0)<div class="text-danger">{{ __('Unpaid days: :days', ['days' => $item->unpaid_days]) }} — {{ number_format($item->unpaid_deduction, 2) }}</div>@endif</td>
                    <td class="text-end fw-semibold">{{ number_format($item->net, 2) }}</td>
                    <td class="text-end text-nowrap no-print">
                        <a href="{{ route('branch.finance.payroll.slips', [$run, 'item' => $item->id]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Salary slip') }}"><i class="bi bi-file-earmark-text"></i></a>
                        @if($run->status === 'draft' && $u->can('payroll.process'))
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#adj{{ $item->id }}" aria-label="{{ __('Adjust') }}"><i class="bi bi-sliders"></i></button>
                        @endif
                    </td>
                </tr>
                @if($run->status === 'draft' && $u->can('payroll.process'))
                    @php
                        $bonus = collect($item->allowances ?? [])->firstWhere('name', __('Bonus / arrear'))['amount'] ?? '';
                        $other = collect($item->deductions ?? [])->firstWhere('name', __('Other deduction'))['amount'] ?? '';
                    @endphp
                    <tr class="collapse no-print" id="adj{{ $item->id }}"><td colspan="7">
                        <form method="POST" action="{{ route('branch.finance.payroll.adjust', [$run, $item]) }}" class="row g-2 align-items-end">
                            @csrf
                            <x-form.input name="bonus" type="number" step="0.01" min="0" :label="__('Bonus / arrear (৳)')" :value="$bonus" col="col-md-3" />
                            <x-form.input name="deduction" type="number" step="0.01" min="0" :label="__('Other deduction (৳)')" :value="$other" col="col-md-3" />
                            <x-form.input name="remarks" :label="__('Remarks')" :value="$item->remarks" col="col-md-4" maxlength="255" />
                            <div class="col-md-2"><button class="btn btn-sm btn-brand w-100">{{ __('Save') }}</button></div>
                        </form>
                    </td></tr>
                @endif
            @endforeach
            </tbody>
            <tfoot class="table-light"><tr><th>{{ __('Total') }}</th><th></th><th></th><th class="text-end">{{ number_format($run->gross, 2) }}</th><th class="text-end">{{ number_format($run->deductions, 2) }}</th><th class="text-end">{{ number_format($run->net, 2) }}</th><th class="no-print"></th></tr></tfoot>
        </table>
    </div>
</div>

<div class="row g-3 no-print">
    @if($run->status === 'draft' && $u->can('payroll.approve'))
        <div class="col-md-4">
            @if($run->prepared_by === $u->id)
                <div class="alert alert-info small">{{ __('The salary sheet must be approved by someone other than the person who prepared it.') }}</div>
            @else
                <form method="POST" action="{{ route('branch.finance.payroll.approve', $run) }}" class="card" data-confirm="{{ __('Approve and post the salary sheet?') }}">
                    @csrf<div class="card-body small">{{ __('Posts the salary expense, the deductions payable and the net salaries payable.') }}</div>
                    <div class="card-footer bg-transparent"><button class="btn btn-success w-100">{{ __('Approve salary sheet') }}</button></div>
                </form>
            @endif
        </div>
    @endif
    @if($run->status === 'approved' && $u->can('payroll.process'))
        <div class="col-md-4">
            <form method="POST" action="{{ route('branch.finance.payroll.pay', $run) }}" class="card" data-confirm="{{ __('Record the salary payment?') }}">
                @csrf
                <div class="card-body row g-2">
                    <x-form.select name="account_id" :label="__('Paid from')" :options="$moneyAccounts->all()" required col="col-12" />
                    <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-12" />
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-brand w-100">{{ __('Record salary payment') }}</button></div>
            </form>
        </div>
    @endif
    @if($run->status !== 'cancelled' && $u->can($run->status === 'draft' ? 'payroll.process' : 'payroll.approve'))
        <div class="col-md-4">
            <form method="POST" action="{{ route('branch.finance.payroll.cancel', $run) }}" class="card" data-confirm="{{ __('Cancel this salary sheet? Posted entries are reversed.') }}">
                @csrf
                <div class="card-body"><x-form.input name="reason" :label="__('Reason')" required col="col-12" minlength="5" maxlength="255" /></div>
                <div class="card-footer bg-transparent"><button class="btn btn-outline-danger w-100">{{ __('Cancel salary sheet') }}</button></div>
            </form>
        </div>
    @endif
    @if($entries->isNotEmpty())
        <div class="col-12">
            <div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Journal entries') }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach($entries as $e)
                        <li class="list-group-item d-flex gap-2"><a href="{{ route('branch.finance.journal.show', $e) }}"><code>{{ $e->entry_no }}</code></a> {{ $e->narration }}<span class="ms-auto">৳{{ number_format($e->amount, 2) }}</span>
                            @if($e->isReversed())<span class="badge text-bg-secondary">{{ __('Reversed') }}</span>@endif</li>
                    @endforeach
                </ul></div>
        </div>
    @endif
</div>
@endsection
