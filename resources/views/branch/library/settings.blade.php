@extends('layouts.branch')
@section('title', __('Library rules'))

@section('content')
<x-page-header :title="__('Library rules')" :breadcrumbs="[__('Library') => route('branch.library.index'), __('Rules') => null]" />
<form method="POST" action="{{ route('branch.library.rules.update') }}" class="card" style="max-width: 900px">
    @csrf @method('PUT')
    <div class="card-body row g-3">
        <div class="col-12 fw-semibold">{{ __('Students') }}</div>
        <x-form.input name="student_max_books" type="number" min="1" :label="__('Books at a time')" :value="$values['student_max_books']" required col="col-md-3" />
        <x-form.input name="student_loan_days" type="number" min="1" :label="__('Loan period (days)')" :value="$values['student_loan_days']" required col="col-md-3" />
        <div class="col-12 fw-semibold">{{ __('Staff') }}</div>
        <x-form.input name="employee_max_books" type="number" min="1" :label="__('Books at a time')" :value="$values['employee_max_books']" required col="col-md-3" />
        <x-form.input name="employee_loan_days" type="number" min="1" :label="__('Loan period (days)')" :value="$values['employee_loan_days']" required col="col-md-3" />
        <div class="col-12 fw-semibold">{{ __('Renewals & fines') }}</div>
        <x-form.input name="max_renewals" type="number" min="0" :label="__('Renewals allowed')" :value="$values['max_renewals']" required col="col-md-3" />
        <x-form.input name="fine_per_day" type="number" step="0.01" min="0" :label="__('Fine per day (৳)')" :value="$values['fine_per_day']" required col="col-md-3" />
        <x-form.input name="grace_days" type="number" min="0" :label="__('Grace days')" :value="$values['grace_days']" required col="col-md-3" />
        <x-form.input name="max_fine_per_loan" type="number" step="0.01" min="0" :label="__('Fine cap per loan (0 = none)')" :value="$values['max_fine_per_loan']" required col="col-md-3" />
        <x-form.input name="lost_fee_percent" type="number" min="0" :label="__('Lost book charge (% of price)')" :value="$values['lost_fee_percent']" required col="col-md-3" />
        <div class="col-12 fw-semibold">{{ __('Reservations & reminders') }}</div>
        <x-form.input name="reservation_hold_days" type="number" min="1" :label="__('Hold a returned book for (days)')" :value="$values['reservation_hold_days']" required col="col-md-3" />
        <x-form.input name="overdue_reminder_days" type="number" min="1" :label="__('Repeat overdue reminder every (days)')" :value="$values['overdue_reminder_days']" required col="col-md-3" />
        <x-form.switch name="block_when_overdue" :label="__('Block new loans while the member has an overdue book')" :checked="$values['block_when_overdue']" />
        <x-form.switch name="block_on_unpaid_fines" :label="__('Block new loans while the member has unpaid fines')" :checked="$values['block_on_unpaid_fines']" />
    </div>
    <x-form.actions :cancel="route('branch.library.index')" />
</form>
@endsection
