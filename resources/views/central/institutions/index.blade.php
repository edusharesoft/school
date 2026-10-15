@extends('layouts.central')
@section('title', __('Institutions'))

@section('content')
<x-page-header :title="__('Institutions')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Institutions') => null]">
    <x-slot:actions>
        @can('create', App\Models\Institution::class)
            <a href="{{ route('central.institutions.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New institution') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search name or code') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button></div>
        </form>
    </div>
    @if($institutions->isEmpty())
        <x-empty-state icon="building" :title="__('No institutions found')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Branches') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($institutions as $institution)
                    <tr>
                        <td><div class="fw-semibold">{{ $institution->name }}</div><div class="small text-body-secondary bn">{{ $institution->name_bn }}</div></td>
                        <td><code>{{ $institution->code }}</code></td>
                        <td>{{ $institution->branches_count }}</td>
                        <td class="small">{{ $institution->email }}<br>{{ $institution->phone }}</td>
                        <td><x-status-badge :status="$institution->status" /></td>
                        <td class="text-end">
                            @can('update', $institution)
                                <a href="{{ route('central.institutions.edit', $institution) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $institutions->links() }}</div>
    @endif
</div>
@endsection
