@extends('layouts.branch')
@section('title', __('Fee settings'))

@php
    $canEdit = auth()->user()->can('fees.update');
    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => __(\Carbon\Carbon::create(2000, $m, 1)->format('F'))])->all();
@endphp

@section('content')
<x-page-header :title="__('Fee settings')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Settings') => null]" />

<form method="POST" action="{{ route('branch.finance.settings.update') }}" class="card" style="max-width: 900px">
    @csrf @method('PUT')
    <fieldset class="card-body row g-3" @disabled(! $canEdit)>
        <div class="col-12 small fw-semibold text-uppercase text-body-secondary">{{ __('Invoices') }}</div>
        <x-form.input name="due_day" type="number" min="1" max="28" :label="__('Monthly due day')" :value="$values['due_day']" required col="col-md-4"
            :help="__('Monthly invoices fall due on this day of the month.')" />
        <x-form.switch name="auto_generate" :label="__('Generate next month\'s invoices automatically (1st of the month, 06:00)')" :checked="$values['auto_generate']" col="col-md-8 d-flex align-items-center" />

        <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('Late fee') }}</div>
        <x-form.select name="late_fee_mode" :label="__('Late fee')" :value="$values['late_fee_mode']" required col="col-md-4"
            :options="['none' => __('No late fee'), 'fixed' => __('Fixed amount (৳)'), 'percent' => __('Percent of the balance')]" />
        <x-form.input name="late_fee_amount" type="number" step="0.01" min="0" :label="__('Amount / percent')" :value="$values['late_fee_amount']" required col="col-md-4" />
        <x-form.input name="late_fee_grace_days" type="number" min="0" max="90" :label="__('Grace days after the due date')" :value="$values['late_fee_grace_days']" required col="col-md-4"
            :help="__('Charged once per invoice by the nightly job.')" />

        <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('Discounts') }}</div>
        <x-form.input name="sibling_discount_percent" type="number" step="0.01" min="0" max="100" :label="__('Automatic sibling discount on tuition (%)')" :value="$values['sibling_discount_percent']" required col="col-md-6"
            :help="__('For the 2nd, 3rd … child who shares a guardian. 0 = off. A manual sibling discount on the student replaces it.')" />

        <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('Accounts & receipts') }}</div>
        <x-form.select name="fy_start_month" :label="__('Financial year starts in')" :value="$values['fy_start_month']" :options="$months" required col="col-md-4" />
        <x-form.input name="receipt_note" :label="__('Note printed on money receipts')" :value="$values['receipt_note']" col="col-md-8" maxlength="255" />

        <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('Payroll') }}</div>
        <x-form.switch name="payroll_deduct_unpaid" :label="__('Deduct absent days without leave and leave without pay from salary')" :checked="$values['payroll_deduct_unpaid']" col="col-md-8 d-flex align-items-center" />
        <x-form.input name="payroll_days_basis" type="number" min="26" max="31" :label="__('Days per month for the daily rate')" :value="$values['payroll_days_basis']" required col="col-md-4"
            :help="__('Daily rate = basic salary ÷ this number.')" />
    </fieldset>
    @if($canEdit)<x-form.actions :cancel="route('branch.finance.invoices.index')" />@endif
</form>
@endsection
