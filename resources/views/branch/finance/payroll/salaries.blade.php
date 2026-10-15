@extends('layouts.branch')
@section('title', __('Salary structures'))

@php
    $canEdit = auth()->user()->can('payroll.process');
    $s = $editing?->salary;
    $allow = old('allowances', $s?->allowances ?? [['name' => __('House rent'), 'amount' => ''], ['name' => __('Medical'), 'amount' => ''], ['name' => __('Conveyance'), 'amount' => '']]);
    $deduct = old('deductions', $s?->deductions ?? [['name' => __('Provident fund'), 'amount' => ''], ['name' => __('Income tax'), 'amount' => '']]);
@endphp

@section('content')
<x-page-header :title="__('Salary structures')" :breadcrumbs="[__('Payroll') => route('branch.finance.payroll.index'), __('Salary structures') => null]" />

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead><tr><th>{{ __('Employee') }}</th><th class="text-end">{{ __('Basic') }}</th><th class="text-end">{{ __('Allowances') }}</th><th class="text-end">{{ __('Deductions') }}</th><th class="text-end">{{ __('Net') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($employees as $e)
                        <tr @class(['table-warning' => ! $e->salary])>
                            <td><div class="fw-semibold">{{ $e->name }}</div><div class="text-body-secondary">{{ $e->employee_code }} · {{ $e->designation?->name }}</div></td>
                            @if($e->salary)
                                <td class="text-end">{{ number_format($e->salary->basic, 2) }}</td>
                                <td class="text-end">{{ number_format($e->salary->allowanceTotal(), 2) }}</td>
                                <td class="text-end">{{ number_format($e->salary->deductionTotal(), 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($e->salary->gross() - $e->salary->deductionTotal(), 2) }}</td>
                            @else
                                <td colspan="4" class="text-body-secondary">{{ __('No salary structure') }}</td>
                            @endif
                            <td class="text-end">@if($canEdit)<a href="{{ route('branch.finance.payroll.salaries', ['employee_id' => $e->id]) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-body-secondary">{{ __('No active employees.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        @if($editing && $canEdit)
            <form method="POST" action="{{ route('branch.finance.payroll.salaries.save', $editing) }}" class="card">
                @csrf
                <div class="card-header bg-transparent fw-semibold">{{ $editing->name }} <span class="small text-body-secondary">{{ $editing->employee_code }}</span></div>
                <div class="card-body row g-2">
                    <x-form.input name="basic" type="number" step="0.01" min="0" :label="__('Basic salary (৳)')" :value="$s?->basic ?? $editing->basic_salary" required col="col-6" />
                    <x-form.input name="effective_from" type="date" :label="__('Effective from')" :value="$s?->effective_from?->toDateString() ?? now()->startOfMonth()->toDateString()" required col="col-6" />
                    <div class="col-12 small fw-semibold mt-2">{{ __('Allowances') }}</div>
                    @for($i = 0; $i < max(4, count($allow) + 1); $i++)
                        <div class="col-7"><input name="allowances[{{ $i }}][name]" value="{{ $allow[$i]['name'] ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('Name') }}" aria-label="{{ __('Allowance') }}"></div>
                        <div class="col-5"><input type="number" step="0.01" min="0" name="allowances[{{ $i }}][amount]" value="{{ $allow[$i]['amount'] ?? '' }}" class="form-control form-control-sm text-end" placeholder="৳" aria-label="{{ __('Amount') }}"></div>
                    @endfor
                    <div class="col-12 small fw-semibold mt-2">{{ __('Deductions') }}</div>
                    @for($i = 0; $i < max(3, count($deduct) + 1); $i++)
                        <div class="col-7"><input name="deductions[{{ $i }}][name]" value="{{ $deduct[$i]['name'] ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('Name') }}" aria-label="{{ __('Deduction') }}"></div>
                        <div class="col-5"><input type="number" step="0.01" min="0" name="deductions[{{ $i }}][amount]" value="{{ $deduct[$i]['amount'] ?? '' }}" class="form-control form-control-sm text-end" placeholder="৳" aria-label="{{ __('Amount') }}"></div>
                    @endfor
                    <x-form.select name="payment_method" :label="__('Paid by')" :value="$s?->payment_method ?? 'bank'" required col="col-6"
                        :options="['bank' => __('Bank'), 'cash' => __('Cash'), 'mobile' => __('Mobile banking')]" />
                    <x-form.input name="account_number" :label="__('Account / wallet no.')" :value="$s?->account_number" col="col-6" maxlength="40" />
                </div>
                <x-form.actions :cancel="route('branch.finance.payroll.salaries')" />
            </form>
        @else
            <div class="card card-body small text-body-secondary">{{ __('Choose an employee to set the basic salary, allowances (house rent, medical …) and deductions (provident fund, tax, loan …).') }}</div>
        @endif
    </div>
</div>
@endsection
