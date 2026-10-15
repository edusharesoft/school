@extends('layouts.branch')
@section('title', __('Chart of accounts'))

@php($canEdit = auth()->user()->can('accounting.post'))

@section('content')
<x-page-header :title="__('Chart of accounts')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Chart of accounts') => null]" />

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Balance (৳)') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach($roots as $root)
                        @include('branch.finance.accounts._row', ['account' => $root, 'depth' => 0])
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent small text-body-secondary">{{ __('Balances come from posted journal entries. Accounts marked with a key are used by the system (fees, advances, discounts …) and keep their role.') }}</div>
        </div>
    </div>
    <div class="col-xl-4">
        @if($canEdit)
            <form method="POST" action="{{ $editing ? route('branch.finance.accounts.update', $editing) : route('branch.finance.accounts.store') }}" class="card">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="card-header bg-transparent fw-semibold">{{ $editing ? __('Edit account') : __('Add account') }}</div>
                <div class="card-body row g-3">
                    <x-form.input name="code" :label="__('Code')" :value="$editing?->code" required col="col-5" maxlength="20" />
                    <x-form.select name="type" :label="__('Type')" :value="$editing?->type ?? 'expense'" required col="col-7"
                        :options="collect(App\Models\Finance\Account::TYPES)->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])->all()" />
                    <x-form.input name="name" :label="__('Name')" :value="$editing?->name" required col="col-12" maxlength="120" />
                    <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$editing?->name_bn" col="col-12" class="bn" maxlength="120" />
                    <x-form.select name="parent_id" :label="__('Under group')" :value="$editing?->parent_id" :options="$groups->all()" :placeholder="__('— Top level —')" col="col-12" />
                    <x-form.select name="kind" :label="__('Money account')" :value="$editing?->kind" :placeholder="__('— No —')" col="col-6"
                        :options="['cash' => __('Cash'), 'bank' => __('Bank'), 'mobile' => __('Mobile banking')]" :help="__('Asset accounts that receive or pay money.')" />
                    <x-form.input name="bank_name" :label="__('Bank / wallet')" :value="$editing?->bank_name" col="col-6" maxlength="100" />
                    <x-form.input name="account_number" :label="__('Account number')" :value="$editing?->account_number" col="col-12" maxlength="40" />
                    <x-form.switch name="is_group" :label="__('Group (holds other accounts, no postings)')" :checked="$editing?->is_group" />
                    <x-form.switch name="is_active" :label="__('Active')" :checked="$editing?->is_active ?? true" />
                </div>
                <x-form.actions :cancel="route('branch.finance.accounts.index')" />
            </form>
        @endif
    </div>
</div>
@endsection
