@extends('layouts.branch')
@section('title', __('Rooms & beds'))

@section('content')
<x-page-header :title="__('Rooms & beds')" :breadcrumbs="[__('Hostel') => route('branch.hostel.index'), __('Rooms') => null]">
    @can('hostel.create')
        <x-slot:actions><a href="{{ route('branch.hostel.rooms.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add room') }}</a></x-slot:actions>
    @endcan
</x-page-header>
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="d-flex gap-2"><select name="hostel_id" class="form-select form-select-sm" style="max-width:240px" aria-label="{{ __('Hostel') }}" onchange="this.form.submit()"><option value="">{{ __('All hostels') }}</option>@foreach($hostels as $id => $name)<option value="{{ $id }}" @selected((int) request('hostel_id') === $id)>{{ $name }}</option>@endforeach</select></form></div>
    @if($rooms->isEmpty())<x-empty-state icon="door-open" :title="__('No rooms yet')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Hostel') }}</th><th>{{ __('Floor') }}</th><th>{{ __('Room') }}</th><th>{{ __('Type') }}</th><th class="text-center">{{ __('Free / beds') }}</th><th class="text-end">{{ __('Fee per bed') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
        <tbody>@foreach($rooms as $r)
            <tr><td>{{ $r->hostel?->name }}</td><td>{{ $r->floor ?? '—' }}</td><td class="fw-semibold"><a href="{{ route('branch.hostel.rooms.show', $r) }}" class="text-decoration-none">{{ $r->room_no }}</a>@unless($r->is_active) <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless</td>
                <td>{{ __(ucfirst($r->type)) }}</td><td class="text-center"><span class="badge text-bg-{{ $r->free_beds > 0 ? 'success' : 'secondary' }}">{{ $r->free_beds }} / {{ $r->beds_count }}</span></td><td class="text-end">৳{{ number_format((float) $r->monthly_fee, 0) }}</td>
                <td><x-row-actions :edit="route('branch.hostel.rooms.edit', $r)" :destroy="route('branch.hostel.rooms.destroy', $r)" :confirm="__('Delete this room?')" :can-edit="auth()->user()->can('hostel.update')" :can-delete="auth()->user()->can('hostel.delete')" /></td></tr>
        @endforeach</tbody></table></div>@endif
</div>
@endsection
