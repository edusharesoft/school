@extends('layouts.branch')
@section('title', __('Departments'))

@php
    $sortLink = fn (string $col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc']);
    $sortIcon = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up opacity-25';
@endphp

@section('content')
<x-page-header :title="__('Departments')" :breadcrumbs="[$currentBranch->name => route('home.redirect'), __('Departments') => null]">
    <x-slot:actions>
        @can('create', App\Models\Department::class)
            <a href="{{ route('branch.departments.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New department') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search name or code') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-3">
                <select name="active" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Any status') }}</option>
                    <option value="1" @selected(request('active') === '1')>{{ __('Active') }}</option>
                    <option value="0" @selected(request('active') === '0')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> {{ __('Filter') }}</button></div>
        </form>
    </div>

    @if($departments->isEmpty())
        <x-empty-state icon="diagram-2" :title="__('No departments yet')" :message="__('Departments group staff for HR, payroll and reporting.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th><a href="{{ $sortLink('name') }}">{{ __('Name') }} <i class="bi {{ $sortIcon('name') }}"></i></a></th>
                    <th><a href="{{ $sortLink('code') }}">{{ __('Code') }} <i class="bi {{ $sortIcon('code') }}"></i></a></th>
                    <th>{{ __('Description') }}</th><th>{{ __('Status') }}</th>
                    <th><a href="{{ $sortLink('created_at') }}">{{ __('Created') }} <i class="bi {{ $sortIcon('created_at') }}"></i></a></th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                @foreach($departments as $department)
                    <tr>
                        <td><div class="fw-semibold">{{ $department->name }}</div><div class="small text-body-secondary bn">{{ $department->name_bn }}</div></td>
                        <td><code>{{ $department->code }}</code></td>
                        <td class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($department->description, 80) }}</td>
                        <td><x-status-badge :status="$department->is_active ? 'active' : 'inactive'" /></td>
                        <td class="small">{{ $department->created_at?->format('d M Y') }}</td>
                        <td class="text-end text-nowrap">
                            @can('update', $department)
                                <a href="{{ route('branch.departments.edit', $department) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @can('delete', $department)
                                <form method="POST" action="{{ route('branch.departments.destroy', $department) }}" class="d-inline" data-confirm="{{ __('Archive this department?') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Archive') }}"><i class="bi bi-archive"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $departments->links() }}</div>
    @endif
</div>
@endsection
