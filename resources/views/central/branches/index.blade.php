@extends('layouts.central')
@section('title', __('Branches'))

@section('content')
<x-page-header :title="__('Branches')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Branches') => null]">
    <x-slot:actions>
        @can('create', App\Models\Branch::class)
            <a href="{{ route('central.branches.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New branch') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search name, code or subdomain') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Any status') }}</option>
                    <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> {{ __('Filter') }}</button></div>
        </form>
    </div>

    @if($branches->isEmpty())
        <x-empty-state icon="diagram-3" :title="__('No branches found')" :message="__('Create the first branch to give it its own subdomain.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Branch') }}</th><th>{{ __('Code') }}</th><th>{{ __('Address (host)') }}</th><th>{{ __('Users') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($branches as $branch)
                    <tr>
                        <td>
                            <a href="{{ route('central.branches.show', $branch) }}" class="fw-semibold text-decoration-none">{{ $branch->name }}</a>
                            <div class="small text-body-secondary">{{ $branch->institution->name }}</div>
                        </td>
                        <td><code>{{ $branch->code }}</code></td>
                        <td class="small"><i class="bi bi-link-45deg"></i> {{ $branch->host() }}</td>
                        <td>{{ $branch->users_count }}</td>
                        <td><x-status-badge :status="$branch->status" /></td>
                        <td class="text-end text-nowrap">
                            @if($branch->isActive())
                                <form method="POST" action="{{ route('central.switch-branch.go', $branch) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="{{ __('Open this branch (secure sign-in)') }}"><i class="bi bi-box-arrow-up-right"></i></button>
                                </form>
                            @endif
                            @can('update', $branch)
                                <a href="{{ route('central.branches.edit', $branch) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $branches->links() }}</div>
    @endif
</div>
@endsection
