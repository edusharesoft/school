@extends('layouts.branch')
@section('title', __('Vehicles'))

@section('content')
<x-page-header :title="__('Vehicles')" :breadcrumbs="[__('Transport') => route('branch.transport.index'), __('Vehicles') => null]">
    @can('transport.create')
        <x-slot:actions><a href="{{ route('branch.transport.vehicles.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add vehicle') }}</a></x-slot:actions>
    @endcan
</x-page-header>
<div class="card">
    @if($vehicles->isEmpty())<x-empty-state icon="truck" :title="__('No vehicles yet')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Registration') }}</th><th>{{ __('Type') }}</th><th class="text-center">{{ __('Seats') }}</th><th>{{ __('Papers') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
        <tbody>@foreach($vehicles as $v)
            @php($exp = $v->expiring(30))
            <tr><td class="fw-semibold"><a href="{{ route('branch.transport.vehicles.show', $v) }}" class="text-decoration-none">{{ $v->registration_no }}</a><div class="text-body-secondary fw-normal">{{ $v->name }}</div></td>
                <td>{{ __(ucfirst($v->type)) }}</td><td class="text-center">{{ $v->capacity }}</td>
                <td>@if($exp)<span class="badge text-bg-warning">{{ count($exp) }} {{ __('expiring') }}</span>@else<span class="badge text-bg-success">{{ __('Valid') }}</span>@endif</td>
                <td><span class="badge text-bg-{{ ['active' => 'success', 'maintenance' => 'warning', 'retired' => 'secondary'][$v->status] }}">{{ __(ucfirst($v->status)) }}</span></td>
                <td><x-row-actions :edit="route('branch.transport.vehicles.edit', $v)" :destroy="route('branch.transport.vehicles.destroy', $v)" :confirm="__('Delete this vehicle?')" :can-edit="auth()->user()->can('transport.update')" :can-delete="auth()->user()->can('transport.delete')" /></td></tr>
        @endforeach</tbody></table></div>@endif
</div>
@endsection
