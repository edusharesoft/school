@extends('layouts.branch')
@section('title', __('Work orders'))

@section('content')
<x-page-header :title="__('Work orders')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Work orders') => null]">
    <x-slot:actions><a href="{{ route('branch.assets.maintenance.plans') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-check me-1"></i>{{ __('Maintenance plans') }}</a></x-slot:actions>
</x-page-header>
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Work order, task or asset') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option><option value="open" @selected(request('status') === 'open')>{{ __('Open') }}</option><option value="completed" @selected(request('status') === 'completed')>{{ __('Completed') }}</option><option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
            <div class="col-md-3"><select name="kind" class="form-select form-select-sm" aria-label="{{ __('Kind') }}"><option value="">{{ __('All kinds') }}</option>@foreach(App\Models\Assets\MaintenanceLog::KINDS as $k)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ __(ucfirst($k)) }}</option>@endforeach</select></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($logs->isEmpty())<x-empty-state icon="tools" :title="__('No work orders')" :message="__('Open one from an asset’s page.')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Asset') }}</th><th>{{ __('Work') }}</th><th>{{ __('Reported') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Cost (৳)') }}</th></tr></thead>
        <tbody>@foreach($logs as $l)<tr>
            <td><a href="{{ route('branch.assets.maintenance.orders.show', $l) }}" class="fw-semibold text-decoration-none">{{ $l->log_no }}</a></td>
            <td><a href="{{ route('branch.assets.register.show', $l->asset_id) }}" class="text-decoration-none">{{ $l->asset?->displayName() }}</a><div class="text-body-secondary">{{ $l->asset?->code }}</div></td>
            <td>{{ $l->title }}<div class="text-body-secondary">{{ __(ucfirst($l->kind)) }}@if($l->takes_offline && $l->isOpen()) · <span class="text-warning">{{ __('out of service') }}</span>@endif</div></td>
            <td class="text-nowrap">{{ $l->reported_on->format('d M Y') }}</td><td><x-inv-status :status="$l->status === 'in_progress' ? 'open' : $l->status" />@if($l->status === 'in_progress') <span class="small text-body-secondary">{{ __('in progress') }}</span>@endif</td>
            <td class="text-end">{{ $l->cost > 0 ? number_format($l->cost, 2) : '—' }}</td></tr>@endforeach</tbody></table></div>
        <div class="card-body">{{ $logs->links() }}</div>@endif
</div>
@endsection
