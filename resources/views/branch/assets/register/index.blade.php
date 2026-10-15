@extends('layouts.branch')
@section('title', __('Asset register'))

@section('content')
<x-page-header :title="__('Asset register')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Asset register') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.assets.categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1"></i>{{ __('Categories') }}</a>
        <a href="{{ route('branch.assets.register.labels', request()->only(['category'])) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upc-scan me-1"></i>{{ __('Print tags') }}</a>
        <a href="{{ route('branch.assets.register.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>{{ __('CSV') }}</a>
        @can('assets.create')<a href="{{ route('branch.assets.register.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Register an asset') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name, tag, serial, brand') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-2"><select name="category" class="form-select form-select-sm" aria-label="{{ __('Category') }}"><option value="">{{ __('All categories') }}</option>@foreach($categories as $id => $name)<option value="{{ $id }}" @selected((int) request('category') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                @foreach(['live' => __('Owned now'), 'in_stock' => __('Available'), 'in_use' => __('In use'), 'repair' => __('Under repair'), 'missing' => __('Missing'), 'disposed' => __('Disposed'), 'voided' => __('Cancelled')] as $k => $label)<option value="{{ $k }}" @selected(request('status', 'live') === $k)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="room" class="form-select form-select-sm" aria-label="{{ __('Room') }}"><option value="">{{ __('All rooms') }}</option>@foreach($rooms as $id => $name)<option value="{{ $id }}" @selected((int) request('room') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="condition" class="form-select form-select-sm" aria-label="{{ __('Condition') }}"><option value="">{{ __('Any condition') }}</option>@foreach(App\Models\Assets\Asset::CONDITIONS as $c)<option value="{{ $c }}" @selected(request('condition') === $c)>{{ __(ucfirst($c)) }}</option>@endforeach</select></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
            <div class="col-12 d-flex flex-wrap gap-3 small">
                <span class="form-check"><input type="checkbox" class="form-check-input" id="lab" name="kind" value="laboratory" @checked(request('kind') === 'laboratory')><label class="form-check-label" for="lab">{{ __('Laboratory equipment only') }}</label></span>
                <span class="form-check"><input type="checkbox" class="form-check-input" id="war" name="warranty" value="1" @checked(request()->boolean('warranty'))><label class="form-check-label" for="war">{{ __('Warranty ending within 60 days') }}</label></span>
                <span class="ms-auto text-body-secondary">{{ __(':n assets · cost :c · book value :b', ['n' => $totals['count'], 'c' => money_bdt($totals['cost']), 'b' => money_bdt($totals['book'])]) }}</span>
            </div>
        </form>
    </div>
    @if($assets->isEmpty())
        <x-empty-state icon="pc-display" :title="__('No assets found')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Tag') }}</th><th>{{ __('Asset') }}</th><th>{{ __('Category') }}</th><th>{{ __('Status') }}</th><th>{{ __('Where / who') }}</th><th class="text-end">{{ __('Cost (৳)') }}</th><th class="text-end">{{ __('Book value (৳)') }}</th></tr></thead>
            <tbody>@foreach($assets as $a)
                <tr>
                    <td class="text-nowrap text-body-secondary">{{ $a->code }}</td>
                    <td><a href="{{ route('branch.assets.register.show', $a) }}" class="fw-semibold text-decoration-none">{{ $a->displayName() }}</a>@if($a->serial_no)<div class="text-body-secondary">{{ $a->serial_no }}</div>@endif</td>
                    <td>{{ $a->category?->name }}@if($a->category?->kind === 'laboratory') <span class="badge text-bg-info">{{ __('Lab') }}</span>@endif</td>
                    <td><x-asset-status :asset="$a" /></td>
                    <td>{{ $a->currentAllocation?->holderLabel() ?? collect([$a->room?->name, $a->location])->filter()->implode(' · ') ?: '—' }}</td>
                    <td class="text-end">{{ number_format($a->cost, 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($a->bookValue(), 2) }}</td>
                </tr>
            @endforeach</tbody></table></div>
        <div class="card-body">{{ $assets->links() }}</div>
    @endif
</div>
@endsection
