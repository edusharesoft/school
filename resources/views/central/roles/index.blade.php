@extends('layouts.central')
@section('title', __('Roles & permissions'))

@section('content')
<x-page-header :title="__('Roles & permissions')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Roles') => null]">
    <x-slot:actions>
        @can('create', Spatie\Permission\Models\Role::class)
            <a href="{{ route('central.roles.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New role') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Portal') }}</th><th>{{ __('Permissions') }}</th><th>{{ __('Users') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($roles as $role)
                <tr>
                    <td><div class="fw-semibold">{{ $role->label ?? $role->name }} @if($role->is_system)<span class="badge text-bg-light border ms-1">{{ __('system') }}</span>@endif</div>
                        <div class="small text-body-secondary"><code>{{ $role->name }}</code> <span class="bn">{{ $role->label_bn }}</span></div></td>
                    <td>{{ $role->scope === 'central' ? __('Institution-wide') : __('Branch') }}</td>
                    <td>{{ __(ucfirst($role->portal)) }}</td>
                    <td>{{ $role->name === 'super-admin' ? __('All (bypass)') : $role->permissions_count }}</td>
                    <td>{{ $role->users_count }}</td>
                    <td class="text-end text-nowrap">
                        @can('update', $role)
                            <a href="{{ route('central.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                        @endcan
                        @if(! $role->is_system)
                            @can('delete', $role)
                                <form method="POST" action="{{ route('central.roles.destroy', $role) }}" class="d-inline" data-confirm="{{ __('Delete this role?') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
