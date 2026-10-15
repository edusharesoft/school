@extends($layout)
@section('title', __('Leave applications'))

@section('content')
<x-page-header :title="__('Leave applications')" :breadcrumbs="[$currentBranch->name => route('home.redirect'), __('Leave applications') => null]">
    <x-slot:actions>
        @can('create', App\Models\HR\LeaveApplication::class)
            @if($own || ($all && $routePrefix === 'branch.'))
                <a href="{{ route($routePrefix.'leaves.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Apply for leave') }}</a>
            @endif
        @endcan
    </x-slot:actions>
</x-page-header>

@if($own && $balances->isNotEmpty())
    <div class="row g-3 mb-4">
        @foreach($balances as $b)
            <div class="col-6 col-md-3">
                <x-stat-card :label="$b['type']->name" icon="fa-solid fa-plane-departure" bg="#bfead6" color="#15803d"
                    :value="$b['remaining'] === null ? '∞' : bn_digits($b['remaining'])"
                    :sub="__('remaining').' · '.__('taken').' '.bn_digits($b['used']).($b['pending'] ? ' · '.__('pending').' '.bn_digits($b['pending']) : '')" />
            </div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="d-flex flex-wrap gap-2">
            @foreach(['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'cancelled' => __('Cancelled'), 'all' => __('All')] as $key => $label)
                <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}" @class(['btn btn-sm', 'btn-brand' => $status === $key, 'btn-outline-secondary' => $status !== $key])>{{ $label }}</a>
            @endforeach
        </form>
    </div>

    @if($applications->isEmpty())
        <x-empty-state icon="calendar2-check" :title="__('No leave applications')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    @if($all)<th>{{ __('Employee') }}</th>@endif
                    <th>{{ __('Leave type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Days') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                @foreach($applications as $la)
                    <tr>
                        @if($all)<td><div class="fw-semibold">{{ $la->employee?->name }}</div><code class="small">{{ $la->employee?->employee_code }}</code></td>@endif
                        <td>{{ $la->leaveType?->name }}</td>
                        <td class="text-nowrap">{{ $la->from_date->format('d M Y') }}@unless($la->from_date->equalTo($la->to_date)) – {{ $la->to_date->format('d M Y') }}@endunless
                            @if($la->is_half_day)<span class="badge text-bg-info">{{ __('Half day') }}</span>@endif</td>
                        <td>{{ bn_digits($la->days) }}</td>
                        <td class="small" style="max-width:260px">{{ $la->reason }}
                            @if($la->review_note)<div class="text-body-secondary fst-italic">{{ $la->reviewer?->name }}: {{ $la->review_note }}</div>@endif</td>
                        <td><span class="badge text-bg-{{ $la->tone() }}">{{ __(ucfirst($la->status)) }}</span></td>
                        <td class="text-end text-nowrap">
                            @if($routePrefix === 'branch.')
                                @can('review', $la)
                                    @if($la->isPending())
                                        <form method="POST" action="{{ route('branch.leaves.approve', $la) }}" class="d-inline">@csrf
                                            <button class="btn btn-sm btn-success" title="{{ __('Approve') }}"><i class="bi bi-check2"></i></button></form>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="{{ __('Reject') }}" data-bs-toggle="collapse" data-bs-target="#rej{{ $la->id }}"><i class="bi bi-x-lg"></i></button>
                                    @endif
                                @endcan
                            @endif
                            @can('cancel', $la)
                                <form method="POST" action="{{ route($routePrefix.'leaves.cancel', $la) }}" class="d-inline" data-confirm="{{ __('Cancel this leave application?') }}">@csrf
                                    <button class="btn btn-sm btn-outline-secondary" title="{{ __('Cancel') }}"><i class="bi bi-slash-circle"></i></button></form>
                            @endcan
                        </td>
                    </tr>
                    @if($routePrefix === 'branch.' && $la->isPending() && auth()->user()->can('review', $la))
                        <tr class="collapse" id="rej{{ $la->id }}"><td colspan="{{ $all ? 7 : 6 }}">
                            <form method="POST" action="{{ route('branch.leaves.reject', $la) }}" class="d-flex gap-2">@csrf
                                <input name="note" class="form-control form-control-sm" placeholder="{{ __('Reason for rejection') }}" required minlength="3" maxlength="500" aria-label="{{ __('Reason for rejection') }}">
                                <button class="btn btn-sm btn-danger">{{ __('Reject') }}</button></form>
                        </td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $applications->links() }}</div>
    @endif
</div>
@endsection
