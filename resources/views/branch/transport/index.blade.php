@extends('layouts.branch')
@section('title', __('Transport'))

@section('content')
<x-page-header :title="__('Transport')" :breadcrumbs="[__('Transport') => null]">
    <x-slot:actions>
        @can('transport.create')<a href="{{ route('branch.transport.routes.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add route') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Vehicles')" :value="$stats['vehicles']" icon="fa-solid fa-bus" :href="route('branch.transport.vehicles.index')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Routes')" :value="$stats['routes']" icon="fa-solid fa-route" bg="#bbf7d0" color="#15803d" :href="route('branch.transport.routes.index')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Students riding')" :value="$stats['riders']" icon="fa-solid fa-user-check" bg="#ddd6fe" color="#6d28d9" :href="route('branch.transport.allocations.index')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Drivers & helpers')" :value="$stats['drivers']" icon="fa-solid fa-id-card" bg="#fde68a" color="#b45309" :href="route('branch.transport.drivers.index')" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card h-100">
        <div class="card-header bg-transparent fw-semibold">{{ __('Seats per route') }}</div>
        @if($routes->isEmpty())<x-empty-state icon="signpost-split" :title="__('No routes yet')" :message="__('Add a route, its stops and a vehicle to start allocating students.')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Route') }}</th><th>{{ __('Vehicle') }}</th><th class="text-end">{{ __('Seats') }}</th></tr></thead>
            <tbody>@foreach($routes as $r)
                @php($cap = $r->vehicle?->capacity)
                <tr><td><a href="{{ route('branch.transport.routes.show', $r) }}">{{ $r->code }} — {{ $r->name }}</a></td><td>{{ $r->vehicle?->registration_no ?? '—' }}</td>
                    <td class="text-end"><span class="badge text-bg-{{ $cap && $r->seats >= $cap ? 'danger' : 'success' }}">{{ $r->seats }}{{ $cap ? ' / '.$cap : '' }}</span></td></tr>
            @endforeach</tbody></table></div>@endif
    </div></div>
    <div class="col-lg-5"><div class="card h-100">
        <div class="card-header bg-transparent fw-semibold">{{ __('Papers expiring within 30 days') }}</div>
        @if($expiring->isEmpty() && $licences->isEmpty())<x-empty-state icon="shield-check" :title="__('All papers are valid')" />
        @else<ul class="list-group list-group-flush small">
            @foreach($expiring as $row)@foreach($row['docs'] as $field => $date)
                <li class="list-group-item d-flex justify-content-between"><span><a href="{{ route('branch.transport.vehicles.show', $row['vehicle']) }}">{{ $row['vehicle']->registration_no }}</a> — {{ __(\App\Models\Transport\Vehicle::DOCUMENTS[$field]) }}</span>
                    <span class="{{ $date->isPast() ? 'text-danger fw-semibold' : 'text-warning' }}">{{ $date->format('d M Y') }}</span></li>
            @endforeach @endforeach
            @foreach($licences as $d)<li class="list-group-item d-flex justify-content-between"><span>{{ $d->name }} — {{ __('Driving licence') }}</span><span class="{{ $d->license_expiry->isPast() ? 'text-danger fw-semibold' : 'text-warning' }}">{{ $d->license_expiry->format('d M Y') }}</span></li>@endforeach
        </ul>@endif
    </div></div>
</div>
@endsection
