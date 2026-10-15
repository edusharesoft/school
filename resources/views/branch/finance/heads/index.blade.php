@extends('layouts.branch')
@section('title', __('Fee heads'))

@section('content')
<x-page-header :title="__('Fee heads')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Fee heads') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.finance.structures.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-grid-3x3 me-1"></i>{{ __('Fee structure') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            @if($heads->isEmpty())
                <x-empty-state icon="tags" :title="__('No fee heads yet')" :message="__('Add the charges you collect: admission, monthly tuition, session, examination, laboratory, library, transport …')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Fee head') }}</th><th>{{ __('Category') }}</th><th>{{ __('Income account') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($heads as $h)
                            <tr>
                                <td><code>{{ $h->code }}</code></td>
                                <td class="fw-semibold">{{ $h->name }}<div class="small text-body-secondary bn">{{ $h->name_bn }}</div></td>
                                <td>{{ __(ucfirst($h->category)) }}</td>
                                <td class="small">{{ $h->incomeAccount?->label() }}</td>
                                <td><x-status-badge :status="$h->is_active ? 'active' : 'inactive'" /></td>
                                <td><x-row-actions :edit="route('branch.finance.heads.index', ['edit' => $h->id])" :can-edit="auth()->user()->can('fees.update')" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        @if($editing ? auth()->user()->can('fees.update') : auth()->user()->can('fees.create'))
            <form method="POST" action="{{ $editing ? route('branch.finance.heads.update', $editing) : route('branch.finance.heads.store') }}" class="card">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="card-header bg-transparent fw-semibold">{{ $editing ? __('Edit fee head') : __('Add fee head') }}</div>
                <div class="card-body row g-3">
                    <x-form.input name="name" :label="__('Name')" :value="$editing?->name" required col="col-12" maxlength="100" :placeholder="__('e.g. Monthly tuition fee')" />
                    <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$editing?->name_bn" col="col-12" class="bn" maxlength="100" />
                    <x-form.input name="code" :label="__('Code')" :value="$editing?->code" required col="col-6" maxlength="20" :placeholder="__('TUI')" />
                    <x-form.input name="sort_order" type="number" :label="__('Order')" :value="$editing?->sort_order ?? 0" min="0" col="col-6" />
                    <x-form.select name="category" :label="__('Category')" :value="$editing?->category ?? 'tuition'" required col="col-12"
                        :options="collect(App\Models\Finance\FeeHead::CATEGORIES)->mapWithKeys(fn ($c) => [$c => __(ucfirst($c))])->all()" />
                    <x-form.select name="income_account_id" :label="__('Income account')" :value="$editing?->income_account_id" :options="$incomeAccounts->all()" col="col-12"
                        :placeholder="__('— Create a new income account —')" />
                    <x-form.switch name="is_active" :label="__('Active')" :checked="$editing?->is_active ?? true" />
                </div>
                <x-form.actions :cancel="route('branch.finance.heads.index')" />
            </form>
        @endif
    </div>
</div>
@endsection
