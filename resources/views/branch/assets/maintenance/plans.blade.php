@extends('layouts.branch')
@section('title', __('Maintenance plans'))

@section('content')
<x-page-header :title="__('Maintenance plans')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Maintenance plans') => null]">
    <x-slot:actions><a href="{{ route('branch.assets.maintenance.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tools me-1"></i>{{ __('Work orders') }}</a></x-slot:actions>
</x-page-header>
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><select name="kind" class="form-select form-select-sm" aria-label="{{ __('Kind') }}"><option value="">{{ __('All kinds') }}</option>@foreach(App\Models\Assets\MaintenancePlan::KINDS as $k)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ __(ucfirst($k)) }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('Active') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option></select></div>
            <div class="col-auto form-check ms-2"><input type="checkbox" class="form-check-input" id="od" name="overdue" value="1" @checked(request()->boolean('overdue'))><label class="form-check-label small" for="od">{{ __('Overdue only') }}</label></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($plans->isEmpty())<x-empty-state icon="calendar-check" :title="__('No plans')" :message="__('Schedule a service or calibration from an asset’s page.')" />
    @else<div class="table-responsive"><table class="table align-middle mb-0 small">
        <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Task') }}</th><th>{{ __('Every') }}</th><th>{{ __('Next due') }}</th><th>{{ __('Responsible') }}</th><th></th></tr></thead>
        <tbody>@foreach($plans as $p)<tr>
            <td><a href="{{ route('branch.assets.register.show', $p->asset_id) }}" class="fw-semibold text-decoration-none">{{ $p->asset?->displayName() }}</a><div class="text-body-secondary">{{ $p->asset?->code }}</div></td>
            <td>{{ $p->title }}<div class="text-body-secondary">{{ __(ucfirst($p->kind)) }}</div></td><td>{{ __(':n month(s)', ['n' => $p->interval_months]) }}</td>
            <td class="text-nowrap @if($p->isOverdue()) text-danger fw-semibold @endif">{{ $p->next_due_on->format('d M Y') }}@if($p->isOverdue())<div class="small">{{ __(':n day(s) overdue', ['n' => abs($p->daysUntilDue())]) }}</div>@endif</td>
            <td>{{ $p->employee?->name ?? '—' }}</td>
            <td class="text-end">@can('assets.update')
                @if($p->is_active)<form method="POST" action="{{ route('branch.assets.maintenance.plans.done', $p) }}" class="d-inline-flex gap-1">@csrf <input type="hidden" name="completed_on" value="{{ now()->toDateString() }}">
                    <input type="number" step="0.01" min="0" name="cost" class="form-control form-control-sm" style="width:90px" placeholder="{{ __('Cost') }}" aria-label="{{ __('Cost') }}">
                    <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2-circle"></i> {{ __('Done today') }}</button></form>@endif
                <form method="POST" action="{{ route('branch.assets.maintenance.plans.update', $p) }}" class="d-inline">@csrf @method('PUT')
                    <input type="hidden" name="title" value="{{ $p->title }}"><input type="hidden" name="kind" value="{{ $p->kind }}"><input type="hidden" name="interval_months" value="{{ $p->interval_months }}"><input type="hidden" name="employee_id" value="{{ $p->employee_id }}"><input type="hidden" name="vendor_id" value="{{ $p->vendor_id }}">
                    <input type="hidden" name="is_active" value="{{ $p->is_active ? 0 : 1 }}"><button class="btn btn-sm btn-outline-secondary">{{ $p->is_active ? __('Pause') : __('Resume') }}</button></form>@endcan</td></tr>@endforeach</tbody></table></div>
        <div class="card-body">{{ $plans->links() }}</div>@endif
</div>
@endsection
