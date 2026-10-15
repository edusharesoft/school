@extends('layouts.branch')
@section('title', __('Asset verification'))

@section('content')
<x-page-header :title="__('Asset verification')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Verification') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        @if($audits->isEmpty())<x-empty-state icon="clipboard-check" :title="__('No verification yet')" :message="__('Start a count sheet to check the register against what is on the shelves.')" />
        @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Title') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Checked') }}</th><th class="text-end">{{ __('Missing') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>@foreach($audits as $a)<tr><td><a href="{{ route('branch.assets.audits.show', $a) }}" class="fw-semibold text-decoration-none">{{ $a->audit_no }}</a></td><td>{{ $a->title }}<div class="text-body-secondary">{{ collect([$a->category?->name, $a->room?->name])->filter()->implode(' · ') ?: __('All assets') }}</div></td>
                <td class="text-nowrap">{{ $a->date->format('d M Y') }}</td><td class="text-end">{{ $a->checked_count }} / {{ $a->items_count }}</td><td class="text-end @if($a->missing_count) text-danger fw-semibold @endif">{{ $a->missing_count }}</td><td><x-inv-status :status="$a->status" /></td></tr>@endforeach</tbody></table></div>
            <div class="card-body">{{ $audits->links() }}</div>@endif
    </div></div>
    @can('assets.update')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.assets.audits.store') }}" class="card">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Start a count sheet') }}</div>
        <div class="card-body row g-3">
            <x-form.input name="title" :label="__('Title')" :value="__('Annual verification :y', ['y' => now()->year])" required col="col-12" maxlength="150" />
            <x-form.input name="date" type="date" :label="__('Date')" :value="now()->toDateString()" required col="col-12" />
            <x-form.select name="asset_category_id" :label="__('Category')" :options="$categories" :placeholder="__('All categories')" col="col-12" />
            <x-form.select name="room_id" :label="__('Room')" :options="$rooms" :placeholder="__('All rooms')" col="col-12" />
            <x-form.input name="note" :label="__('Note')" maxlength="255" col="col-12" />
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Start') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
