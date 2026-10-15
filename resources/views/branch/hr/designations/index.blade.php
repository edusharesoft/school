@extends('layouts.branch')
@section('title', __('Designations'))

@section('content')
<x-page-header :title="__('Designations')" :breadcrumbs="[__('Staff & HR') => route('branch.hr.employees.index'), __('Designations') => null]">
    <x-slot:actions>
        @can('employees.update')
            <a href="{{ route('branch.hr.designations.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New designation') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    @if($designations->isEmpty())
        <x-empty-state icon="award" :title="__('No designations yet')" :message="__('Create job titles such as Head Teacher, Assistant Teacher, Office Assistant.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Rank') }}</th><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Category') }}</th><th>{{ __('Active employees') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($designations as $d)
                    <tr>
                        <td>{{ bn_digits($d->rank) }}</td>
                        <td><div class="fw-semibold">{{ $d->name }}</div><div class="small text-body-secondary bn">{{ $d->name_bn }}</div></td>
                        <td><code>{{ $d->code }}</code></td>
                        <td>{{ $d->category === 'teaching' ? __('Teaching') : __('Non-teaching') }}</td>
                        <td>{{ bn_digits($d->employees_count) }}</td>
                        <td><x-status-badge :status="$d->is_active ? 'active' : 'inactive'" /></td>
                        <td>
                            <x-row-actions :edit="route('branch.hr.designations.edit', $d)" :destroy="route('branch.hr.designations.destroy', $d)"
                                :can-edit="auth()->user()->can('employees.update')" :can-delete="auth()->user()->can('employees.update')"
                                :confirm="__('Archive this designation?')" />
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
