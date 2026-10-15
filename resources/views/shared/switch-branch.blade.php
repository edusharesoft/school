@extends($layout)
@section('title', __('Switch branch'))

@section('content')
<x-page-header :title="__('Choose a branch')" :breadcrumbs="[__('Switch branch') => null]" />

<p class="text-body-secondary small">{{ __('You will be signed in to the selected branch with a one-time secure link. Every switch is recorded in the audit log.') }}</p>

@if($branches->isEmpty())
    <x-empty-state icon="diagram-3" :title="__('No branches available')" :message="__('Ask an administrator to grant you access to a branch.')" />
@else
    <div class="row g-3">
        @foreach($branches as $b)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ $currentBranchId === $b->id ? 'border-success' : '' }}">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <span class="avatar" style="background: {{ $b->brandColor() }}">{{ mb_substr($b->name, 0, 1) }}</span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $b->displayName() }}</div>
                            <div class="small text-body-secondary">{{ $b->host() }}</div>
                        </div>
                        @if($currentBranchId === $b->id)
                            <span class="badge text-bg-success">{{ __('Current') }}</span>
                        @else
                            <form method="POST" action="{{ tenant()->isCentral() ? route('central.switch-branch.go', $b) : route('branch.switch-branch.go', $b) }}">
                                @csrf
                                <button class="btn btn-sm btn-brand">{{ __('Open') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($canUseCentral && tenant()->isBranch())
    <form method="POST" action="{{ route('branch.switch-central') }}" class="mt-4">@csrf
        <button class="btn btn-outline-secondary"><i class="bi bi-globe2 me-1"></i>{{ __('Go to central administration') }}</button>
    </form>
@endif
@endsection
