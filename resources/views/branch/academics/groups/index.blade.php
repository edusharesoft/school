@extends('layouts.branch')
@section('title', __('Groups'))

@section('content')
<x-page-header :title="__('Groups')" :breadcrumbs="[__('Academics') => null, __('Groups') => null]">
    <x-slot:actions>
        @can('create', App\Models\Academic\StudyGroup::class)
            <a href="{{ route('branch.academics.groups.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New group') }}</a>
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
    @if($items->isEmpty())
        <x-empty-state icon="collection" :title="__('Nothing here yet')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Group') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td><code>{{ $item->code }}</code></td><td><div class="fw-semibold">{{ $item->name }}</div><div class="small text-body-secondary bn">{{ $item->name_bn }}</div></td>
                        <td><x-status-badge :status="$item->is_active ? 'active' : 'inactive'" /></td>
                        <td><x-row-actions :edit="route('branch.academics.groups.edit', $item)" :destroy="route('branch.academics.groups.destroy', $item)"
                            :can-edit="auth()->user()->can('update', $item)" :can-delete="auth()->user()->can('delete', $item)" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $items->links() }}</div>
    @endif
</div>
@endsection
