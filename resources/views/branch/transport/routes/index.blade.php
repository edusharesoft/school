@extends('layouts.branch')
@section('title', __('Routes & stops'))

@section('content')
<x-page-header :title="__('Routes & stops')" :breadcrumbs="[__('Transport') => route('branch.transport.index'), __('Routes') => null]">
    @can('transport.create')
        <x-slot:actions><a href="{{ route('branch.transport.routes.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add route') }}</a></x-slot:actions>
    @endcan
</x-page-header>
<div class="card">
    @if($routes->isEmpty())<x-empty-state icon="signpost-split" :title="__('No routes yet')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Route') }}</th><th>{{ __('Vehicle') }}</th><th>{{ __('Driver') }}</th><th class="text-center">{{ __('Stops') }}</th><th class="text-center">{{ __('Seats') }}</th><th class="text-end">{{ __('Fee') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
        <tbody>@foreach($routes as $r)
            <tr><td class="fw-semibold"><a href="{{ route('branch.transport.routes.show', $r) }}" class="text-decoration-none">{{ $r->code }} — {{ $r->name }}</a>@unless($r->is_active) <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless</td>
                <td>{{ $r->vehicle?->registration_no ?? '—' }}</td><td>{{ $r->driver?->name ?? '—' }}<div class="text-body-secondary">{{ $r->driver?->phone }}</div></td>
                <td class="text-center">{{ $r->stops_count }}</td><td class="text-center">{{ $r->seats }}{{ $r->vehicle ? ' / '.$r->vehicle->capacity : '' }}</td><td class="text-end">৳{{ number_format((float) $r->monthly_fee, 0) }}</td>
                <td><x-row-actions :edit="route('branch.transport.routes.edit', $r)" :destroy="route('branch.transport.routes.destroy', $r)" :confirm="__('Delete this route?')" :can-edit="auth()->user()->can('transport.update')" :can-delete="auth()->user()->can('transport.delete')" /></td></tr>
        @endforeach</tbody></table></div>@endif
</div>
@endsection
