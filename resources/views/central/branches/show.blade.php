@extends('layouts.central')
@section('title', $branch->name)

@section('content')
<x-page-header :title="$branch->name" :breadcrumbs="[__('Branches') => route('central.branches.index'), $branch->code => null]">
    <x-slot:actions>
        @if($branch->isActive())
            <form method="POST" action="{{ route('central.switch-branch.go', $branch) }}">@csrf
                <button class="btn btn-brand btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Open branch') }}</button>
            </form>
        @endif
        @can('update', $branch)
            <a href="{{ route('central.branches.edit', $branch) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
            <form method="POST" action="{{ route('central.branches.status', $branch) }}"
                  data-confirm="{{ $branch->isActive() ? __('Deactivate this branch? Its users will not be able to sign in.') : __('Activate this branch?') }}">
                @csrf @method('PATCH')
                <button class="btn btn-sm {{ $branch->isActive() ? 'btn-outline-danger' : 'btn-outline-success' }}">
                    {{ $branch->isActive() ? __('Deactivate') : __('Activate') }}
                </button>
            </form>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Profile') }}</h2></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">{{ __('Institution') }}</dt><dd class="col-7">{{ $branch->institution->name }}</dd>
                    <dt class="col-5">{{ __('Bangla name') }}</dt><dd class="col-7 bn">{{ $branch->name_bn ?: '—' }}</dd>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$branch->status" /></dd>
                    <dt class="col-5">{{ __('Address (host)') }}</dt><dd class="col-7"><code>{{ $branch->host() }}</code></dd>
                    <dt class="col-5">{{ __('Principal') }}</dt><dd class="col-7">{{ $branch->principal_name ?: '—' }}</dd>
                    <dt class="col-5">{{ __('Contact') }}</dt><dd class="col-7">{{ $branch->email ?: '—' }}<br>{{ $branch->phone }}</dd>
                    <dt class="col-5">{{ __('Time zone / currency') }}</dt><dd class="col-7">{{ $branch->timezone }} / {{ $branch->currency }}</dd>
                    <dt class="col-5">{{ __('Users with access') }}</dt><dd class="col-7">{{ $branch->users_count }}</dd>
                    <dt class="col-5">{{ __('Departments') }}</dt><dd class="col-7">{{ $branch->departments_count }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Domains') }}</h2></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex small"><span><i class="bi bi-link-45deg"></i> {{ $branch->subdomain }}.{{ config('tenancy.base_domain') }}</span><span class="ms-auto badge text-bg-light border">{{ __('subdomain') }}</span></li>
                @foreach($branch->domains as $domain)
                    <li class="list-group-item d-flex align-items-center small">
                        <span><i class="bi bi-globe"></i> {{ $domain->domain }} @if($domain->is_primary)<span class="badge text-bg-success ms-1">{{ __('primary') }}</span>@endif</span>
                        @can('manageDomains', $branch)
                            <form method="POST" action="{{ route('central.branches.domains.destroy', [$branch, $domain]) }}" class="ms-auto" data-confirm="{{ __('Remove this domain?') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0">{{ __('Remove') }}</button>
                            </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
            @can('manageDomains', $branch)
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('central.branches.domains.store', $branch) }}" class="row g-2">
                        @csrf
                        <div class="col-12"><input name="domain" class="form-control form-control-sm" placeholder="dhanmondi.example.edu.bd" required aria-label="{{ __('Custom domain') }}"></div>
                        <div class="col-auto form-check ms-2"><input class="form-check-input" type="checkbox" name="is_primary" value="1" id="is_primary"><label class="form-check-label small" for="is_primary">{{ __('Make primary') }}</label></div>
                        <div class="col-auto ms-auto"><button class="btn btn-sm btn-outline-secondary">{{ __('Add domain') }}</button></div>
                    </form>
                </div>
            @endcan
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-transparent d-flex"><h2 class="h6 mb-0">{{ __('Recent activity') }}</h2>
                @can('audit-logs.view')<a class="ms-auto small" href="{{ route('central.audit-logs.index', ['branch_id' => $branch->id]) }}">{{ __('View all') }}</a>@endcan
            </div>
            @if($activity->isEmpty())
                <x-empty-state icon="clock-history" :title="__('No activity yet')" />
            @else
                <ul class="list-group list-group-flush">
                    @foreach($activity as $log)
                        <li class="list-group-item small d-flex gap-2">
                            <span class="badge text-bg-light border">{{ $log->event }}</span>
                            <span>{{ $log->user?->name ?? __('System') }}</span>
                            <span class="ms-auto text-body-secondary" title="{{ $log->created_at }}">{{ $log->created_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
