@extends('layouts.branch')
@section('title', __('Drivers & helpers'))

@section('content')
<x-page-header :title="__('Drivers & helpers')" :breadcrumbs="[__('Transport') => route('branch.transport.index'), __('Drivers & helpers') => null]">
    @can('transport.create')
        <x-slot:actions><a href="{{ route('branch.transport.drivers.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add person') }}</a></x-slot:actions>
    @endcan
</x-page-header>
<div class="card">
    @if($drivers->isEmpty())<x-empty-state icon="person-badge" :title="__('No drivers yet')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Licence') }}</th><th>{{ __('Emergency contact') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
        <tbody>@foreach($drivers as $d)
            <tr><td class="fw-semibold">{{ $d->name }}@unless($d->is_active) <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless</td><td>{{ __(ucfirst($d->role)) }}</td><td>{{ $d->phone }}</td>
                <td>{{ $d->license_no ?? '—' }}@if($d->license_expiry)<div class="{{ $d->license_expiry->isPast() ? 'text-danger fw-semibold' : 'text-body-secondary' }}">{{ $d->license_expiry->format('d M Y') }}</div>@endif</td>
                <td>{{ $d->emergency_contact_name ?? '—' }}<div class="text-body-secondary">{{ $d->emergency_contact_phone }}</div></td>
                <td><x-row-actions :edit="route('branch.transport.drivers.edit', $d)" :destroy="route('branch.transport.drivers.destroy', $d)" :confirm="__('Delete this person?')" :can-edit="auth()->user()->can('transport.update')" :can-delete="auth()->user()->can('transport.delete')" /></td></tr>
        @endforeach</tbody></table></div>@endif
</div>
@endsection
