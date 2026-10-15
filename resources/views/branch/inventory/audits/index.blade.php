@extends('layouts.branch')
@section('title', __('Stock audits'))

@section('content')
<x-page-header :title="__('Stock audits')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Stock audits') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        @if($audits->isEmpty())<x-empty-state icon="clipboard-check" :title="__('No stock audits yet')" :message="__('Open a count sheet, count the shelves, then complete the audit to post the differences.')" />
        @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Audit') }}</th><th>{{ __('Date') }}</th><th>{{ __('Scope') }}</th><th class="text-end">{{ __('Counted') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>@foreach($audits as $a)<tr><td><a href="{{ route('branch.inventory.audits.show', $a) }}" class="fw-semibold text-decoration-none">{{ $a->audit_no }}</a><div class="text-body-secondary">{{ $a->title }}</div></td>
                <td class="text-nowrap">{{ $a->date->format('d M Y') }}</td><td>{{ $a->category?->name ?? __('All items') }}</td><td class="text-end">{{ $a->counted_count }} / {{ $a->items_count }}</td><td><x-inv-status :status="$a->status" /></td></tr>@endforeach</tbody></table></div>
        <div class="card-body">{{ $audits->links() }}</div>@endif
    </div></div>
    @can('inventory.create')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.inventory.audits.store') }}" class="card">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Open a count sheet') }}</div>
        <div class="card-body row g-3">
            <x-form.input name="title" :label="__('Title')" required col="col-12" maxlength="150" :placeholder="__('e.g. Annual count — chemistry lab')" />
            <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-12" />
            <x-form.select name="inventory_category_id" :label="__('Category')" :options="$categories" :placeholder="__('All items')" col="col-12" />
            <x-form.input name="note" :label="__('Note')" maxlength="255" col="col-12" />
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Open count sheet') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
