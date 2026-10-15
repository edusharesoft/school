@extends('layouts.branch')
@section('title', __('Suppliers'))

@section('content')
<x-page-header :title="__('Suppliers')" :breadcrumbs="[__('Expenses') => route('branch.finance.expenses.index'), __('Suppliers') => null]" />

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body border-bottom">
                <form method="GET" class="d-flex gap-2"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name or mobile') }}" aria-label="{{ __('Search') }}">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button></form>
            </div>
            @if($vendors->isEmpty())
                <x-empty-state icon="shop" :title="__('No suppliers yet')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Contact') }}</th><th>{{ __('BIN / TIN') }}</th><th class="text-end">{{ __('Expenses') }}</th><th class="text-end">{{ __('Spent (৳)') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($vendors as $v)
                            <tr @class(['text-body-tertiary' => ! $v->is_active])>
                                <td class="fw-semibold">{{ $v->name }}<div class="fw-normal text-body-secondary">{{ $v->address }}</div></td>
                                <td>{{ $v->phone }}<div>{{ $v->email }}</div></td>
                                <td>{{ $v->bin }}</td>
                                <td class="text-end">{{ $v->expenses_count }}</td>
                                <td class="text-end">{{ number_format((float) $v->spent, 2) }}</td>
                                <td><x-row-actions :edit="route('branch.finance.vendors.index', ['edit' => $v->id])" :can-edit="auth()->user()->can('expenses.update')" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body">{{ $vendors->links() }}</div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        @if($editing ? auth()->user()->can('expenses.update') : auth()->user()->can('expenses.create'))
            <form method="POST" action="{{ $editing ? route('branch.finance.vendors.update', $editing) : route('branch.finance.vendors.store') }}" class="card">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="card-header bg-transparent fw-semibold">{{ $editing ? __('Edit supplier') : __('Add supplier') }}</div>
                <div class="card-body row g-3">
                    <x-form.input name="name" :label="__('Name')" :value="$editing?->name" required col="col-12" maxlength="150" />
                    <x-form.input name="phone" :label="__('Mobile')" :value="$editing?->phone" col="col-6" maxlength="20" />
                    <x-form.input name="bin" :label="__('BIN / TIN')" :value="$editing?->bin" col="col-6" maxlength="30" />
                    <x-form.input name="email" type="email" :label="__('E-mail')" :value="$editing?->email" col="col-12" />
                    <x-form.input name="address" :label="__('Address')" :value="$editing?->address" col="col-12" maxlength="255" />
                    <x-form.switch name="is_active" :label="__('Active')" :checked="$editing?->is_active ?? true" />
                </div>
                <x-form.actions :cancel="route('branch.finance.vendors.index')" />
            </form>
        @endif
    </div>
</div>
@endsection
