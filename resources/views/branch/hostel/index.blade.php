@extends('layouts.branch')
@section('title', __('Hostel'))

@section('content')
<x-page-header :title="__('Hostel')" :breadcrumbs="[__('Hostel') => null]">
    @can('hostel.create')
        <x-slot:actions><a href="{{ route('branch.hostel.buildings.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add hostel') }}</a></x-slot:actions>
    @endcan
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Hostels')" :value="$hostels->count()" icon="fa-solid fa-building" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Beds')" :value="$stats['beds']" icon="fa-solid fa-bed" bg="#bbf7d0" color="#15803d" :sub="$stats['occupied'].' '.__('occupied')" :href="route('branch.hostel.rooms.index')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Visitors inside')" :value="$stats['visitors']" icon="fa-solid fa-people-arrows" bg="#ddd6fe" color="#6d28d9" :href="route('branch.hostel.visitors.index', ['inside' => 1])" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Open repair requests')" :value="$stats['requests']" icon="fa-solid fa-screwdriver-wrench" bg="#fde68a" color="#b45309" :href="route('branch.hostel.maintenance.index')" /></div>
</div>
<div class="card">
    @if($hostels->isEmpty())<x-empty-state icon="building" :title="__('No hostels yet')" :message="__('Add a hostel, then its rooms and beds.')" />
    @else<div class="table-responsive"><table class="table align-middle mb-0 small">
        <thead><tr><th>{{ __('Hostel') }}</th><th>{{ __('Type') }}</th><th>{{ __('Warden') }}</th><th class="text-center">{{ __('Occupancy') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
        <tbody>@foreach($hostels as $h)
            <tr><td class="fw-semibold">{{ $h->name }}@unless($h->is_active) <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless<div class="text-body-secondary fw-normal">{{ $h->address }}</div></td>
                <td>{{ __(ucfirst($h->type)) }}</td><td>{{ $h->warden?->name ?? '—' }}<div class="text-body-secondary">{{ $h->warden?->phone ?? $h->phone }}</div></td>
                <td class="text-center"><span class="badge text-bg-{{ $h->occupancy['free'] === 0 && $h->occupancy['beds'] > 0 ? 'danger' : 'success' }}">{{ $h->occupancy['occupied'] }} / {{ $h->occupancy['beds'] }}</span></td>
                <td><x-row-actions :edit="route('branch.hostel.buildings.edit', $h)" :destroy="route('branch.hostel.buildings.destroy', $h)" :confirm="__('Delete this hostel and its rooms?')" :can-edit="auth()->user()->can('hostel.update')" :can-delete="auth()->user()->can('hostel.delete')"><a href="{{ route('branch.hostel.rooms.index', ['hostel_id' => $h->id]) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Rooms') }}"><i class="bi bi-door-open"></i></a></x-row-actions></td></tr>
        @endforeach</tbody></table></div>@endif
</div>
@endsection
