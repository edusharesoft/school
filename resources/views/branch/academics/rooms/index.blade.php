@extends('layouts.branch')
@section('title', __('Rooms'))

@section('content')
<x-page-header :title="__('Rooms')" :breadcrumbs="[__('Academics') => null, __('Rooms') => null]">
    <x-slot:actions>
        @can('create', App\Models\Academic\Room::class)
            <a href="{{ route('branch.academics.rooms.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New room') }}</a>
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
        <x-empty-state icon="door-open" :title="__('Nothing here yet')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Room') }}</th><th>{{ __('Type') }}</th><th>{{ __('Capacity') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td><code>{{ $item->code }}</code></td><td><div class="fw-semibold">{{ $item->name }}</div><div class="small text-body-secondary">{{ $item->building }}</div></td><td>{{ __(ucfirst($item->type)) }}</td><td>{{ $item->capacity }}</td>
                        <td><x-status-badge :status="$item->is_active ? 'active' : 'inactive'" /></td>
                        <td><x-row-actions :edit="route('branch.academics.rooms.edit', $item)" :destroy="route('branch.academics.rooms.destroy', $item)"
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
