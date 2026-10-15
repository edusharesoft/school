@extends('layouts.branch')
@section('title', __('Maintenance report'))

@section('content')
<x-page-header :title="__('Asset reports')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Reports') => route('branch.assets.reports.summary'), __('Maintenance') => null]" />
@include('branch.assets.reports._nav')
<div class="card mb-3"><div class="card-header bg-transparent fw-semibold">{{ __('Schedule — what is due') }}</div>
    @if($plans->isEmpty())<x-empty-state icon="calendar-check" :title="__('No recurring tasks')" />
    @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
        <thead><tr><th>{{ __('Next due') }}</th><th>{{ __('Asset') }}</th><th>{{ __('Task') }}</th><th>{{ __('Responsible') }}</th></tr></thead>
        <tbody>@foreach($plans as $p)<tr class="@if($p->isOverdue()) table-danger @endif"><td class="text-nowrap">{{ $p->next_due_on->format('d M Y') }}@if($p->isOverdue()) <span class="small">({{ __(':n day(s) overdue', ['n' => abs($p->daysUntilDue())]) }})</span>@endif</td>
            <td><a href="{{ route('branch.assets.register.show', $p->asset_id) }}" class="text-decoration-none">{{ $p->asset?->displayName() }}</a> <span class="text-body-secondary">{{ $p->asset?->code }}</span></td><td>{{ $p->title }} <span class="text-body-secondary">({{ __(ucfirst($p->kind)) }})</span></td><td>{{ $p->employee?->name ?? '—' }}</td></tr>@endforeach</tbody></table></div>@endif
</div>
<div class="card mb-3"><div class="card-body"><form method="GET" class="row g-2 align-items-end">
    <div class="col-auto"><label class="form-label small mb-0" for="f">{{ __('Completed from') }}</label><input id="f" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><label class="form-label small mb-0" for="t">{{ __('to') }}</label><input id="t" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    <div class="col text-md-end small text-body-secondary">{{ __(':n jobs · cost ৳:c (recorded for reporting, not posted to the books)', ['n' => $logs->count(), 'c' => number_format($total, 2)]) }}</div>
</form></div></div>
<div class="row g-3">
    <div class="col-lg-4"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('By kind') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Kind') }}</th><th class="text-end">{{ __('Jobs') }}</th><th class="text-end">{{ __('Cost (৳)') }}</th></tr></thead>
            <tbody>@forelse($byKind as $k)<tr><td>{{ __(ucfirst($k['kind'])) }}</td><td class="text-end">{{ $k['count'] }}</td><td class="text-end">{{ number_format($k['cost'], 2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-body-secondary py-3">—</td></tr>@endforelse</tbody></table></div></div>
        <div class="card mt-3"><div class="card-header bg-transparent fw-semibold">{{ __('Costliest assets') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small"><tbody>@forelse($byAsset as $a)<tr><td>{{ $a['name'] }}<div class="text-body-secondary">{{ $a['code'] }}</div></td><td class="text-end">{{ $a['count'] }}×</td><td class="text-end">{{ number_format($a['cost'], 2) }}</td></tr>@empty<tr><td class="text-center text-body-secondary py-3">—</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-8"><div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Completed jobs') }}</div>
        @if($logs->isEmpty())<x-empty-state icon="tools" :title="__('No completed jobs in this period')" />
        @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small"><thead><tr><th>{{ __('Done') }}</th><th>{{ __('Job') }}</th><th>{{ __('Asset') }}</th><th class="text-end">{{ __('Cost (৳)') }}</th></tr></thead>
            <tbody>@foreach($logs as $l)<tr><td class="text-nowrap">{{ $l->completed_on->format('d M Y') }}</td><td><a href="{{ route('branch.assets.maintenance.orders.show', $l) }}" class="text-decoration-none">{{ $l->log_no }}</a> {{ $l->title }}<div class="text-body-secondary">{{ __(ucfirst($l->kind)) }}@if($l->reference) · {{ $l->reference }}@endif</div></td><td>{{ $l->asset?->displayName() }}</td><td class="text-end">{{ number_format($l->cost, 2) }}</td></tr>@endforeach</tbody></table></div>@endif
    </div></div>
</div>
@endsection
