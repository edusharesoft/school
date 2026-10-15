@extends('layouts.central')
@section('title', __('Users'))

@section('content')
<x-page-header :title="__('Users')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Users') => null]">
    <x-slot:actions>
        @can('create', App\Models\User::class)
            <a href="{{ route('central.users.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-person-plus me-1"></i>{{ __('New user') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name, e-mail, phone, username') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm" aria-label="{{ __('Type') }}"><option value="">{{ __('Any type') }}</option>
                    @foreach($types as $v => $l)<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>@endforeach</select>
            </div>
            <div class="col-6 col-md-2">
                <select name="role" class="form-select form-select-sm" aria-label="{{ __('Role') }}"><option value="">{{ __('Any role') }}</option>
                    @foreach($roles as $v => $l)<option value="{{ $v }}" @selected(request('role') === $v)>{{ $l }}</option>@endforeach</select>
            </div>
            <div class="col-md-3">
                <select name="branch_id" class="form-select form-select-sm" aria-label="{{ __('Branch') }}"><option value="">{{ __('Any branch') }}</option>
                    @foreach($branches as $id => $name)<option value="{{ $id }}" @selected((int) request('branch_id') === $id)>{{ $name }}</option>@endforeach</select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button></div>
        </form>
    </div>

    @if($users->isEmpty())
        <x-empty-state icon="people" :title="__('No users match these filters')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('User') }}</th><th>{{ __('Type') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Branches') }}</th><th>{{ __('Last sign-in') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($users as $u)
                    <tr>
                        <td class="d-flex align-items-center gap-2">
                            <span class="avatar">{{ $u->initials() }}</span>
                            <div><div class="fw-semibold">{{ $u->name }}</div><div class="small text-body-secondary">{{ $u->email }}</div></div>
                        </td>
                        <td>{{ $u->user_type?->label() }}</td>
                        <td class="small">{{ $u->roleLabels() }}</td>
                        <td class="small">
                            @if($u->canAccessAllBranches())
                                <span class="badge text-bg-info">{{ __('All branches') }}</span>
                            @else
                                {{ $u->branches->pluck('name')->take(3)->implode(', ') }}@if($u->branches->count() > 3) +{{ $u->branches->count() - 3 }}@endif
                            @endif
                        </td>
                        <td class="small text-body-secondary">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td><x-status-badge :status="$u->status" /></td>
                        <td class="text-end">
                            @can('update', $u)
                                <a href="{{ route('central.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                @if($u->hasTwoFactor() && ! $u->is(auth()->user()))<form method="POST" action="{{ route('central.users.two-factor.reset', $u) }}" class="d-inline" onsubmit="return confirm('{{ __('Reset two-factor authentication for this user?') }}')">@csrf<button class="btn btn-sm btn-outline-warning" title="{{ __('Reset two-factor') }}" aria-label="{{ __('Reset two-factor') }}"><i class="bi bi-shield-x"></i></button></form>@endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $users->links() }}</div>
    @endif
</div>
@endsection
