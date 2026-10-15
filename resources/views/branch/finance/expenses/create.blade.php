@extends('layouts.branch')
@section('title', __('New expense'))

@section('content')
<x-page-header :title="__('New expense')" :breadcrumbs="[__('Expenses') => route('branch.finance.expenses.index'), __('New') => null]" />

<form method="POST" action="{{ route('branch.finance.expenses.store') }}" enctype="multipart/form-data" class="card" style="max-width: 860px">
    @csrf
    <div class="card-body row g-3">
        <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-md-3" />
        <x-form.select name="account_id" :label="__('Expense account')" :options="$accounts->all()" required col="col-md-5" :placeholder="__('— Choose —')" />
        <x-form.input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount (৳)')" required col="col-md-4" />
        <x-form.input name="description" :label="__('Description')" required col="col-12" maxlength="255" :placeholder="__('e.g. Electricity bill for June')" />
        <x-form.select name="vendor_id" :label="__('Supplier / payee')" :options="$vendors->all()" :placeholder="__('— None —')" col="col-md-6" />
        <x-form.input name="reference" :label="__('Bill / cheque / transaction no.')" col="col-md-6" maxlength="100" />
        <x-form.select name="payment_mode" :label="__('Payment')" required col="col-md-4" :value="'paid'"
            :options="['paid' => __('Paid now'), 'credit' => __('On credit (pay later)')]" />
        <x-form.select name="paid_from_account_id" :label="__('Paid from')" :options="$moneyAccounts->all()" :placeholder="__('— Choose —')" col="col-md-8"
            :help="__('Required when paid now.')" />
        <div class="col-12">
            <label class="form-label" for="attachment">{{ __('Bill / receipt (PDF or image, max 5 MB)') }}</label>
            <input type="file" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png" @class(['form-control', 'is-invalid' => $errors->has('attachment')])>
            @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 small text-body-secondary">{{ __('The expense is posted to the accounts only after another authorised person approves it.') }}</div>
    </div>
    <x-form.actions :cancel="route('branch.finance.expenses.index')">{{ __('Submit for approval') }}</x-form.actions>
</form>
@endsection
