@extends('layouts.branch')
@section('title', $log->log_no)

@section('content')
@php $canUpdate = auth()->user()->can('assets.update'); @endphp
<x-page-header :title="$log->log_no" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Work orders') => route('branch.assets.maintenance.index'), $log->log_no => null]" />
<div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-body small">
        <div class="mb-3 d-flex gap-2 align-items-center"><x-inv-status :status="$log->status === 'in_progress' ? 'open' : $log->status" />@if($log->status === 'in_progress')<span>{{ __('in progress') }}</span>@endif<span class="badge text-bg-light border">{{ __(ucfirst($log->kind)) }}</span>@if($log->takes_offline && $log->isOpen())<span class="badge text-bg-warning">{{ __('Out of service') }}</span>@endif</div>
        <h2 class="h6">{{ $log->title }}</h2>
        @if($log->description)<p>{{ $log->description }}</p>@endif
        <dl class="row mb-0">
            <dt class="col-4">{{ __('Asset') }}</dt><dd class="col-8"><a href="{{ route('branch.assets.register.show', $log->asset_id) }}">{{ $log->asset?->displayName() }}</a> <span class="text-body-secondary">{{ $log->asset?->code }}</span></dd>
            @if($log->plan)<dt class="col-4">{{ __('Plan') }}</dt><dd class="col-8">{{ $log->plan->title }} · {{ __('every :n month(s)', ['n' => $log->plan->interval_months]) }}</dd>@endif
            <dt class="col-4">{{ __('Reported') }}</dt><dd class="col-8">{{ $log->reported_on->format('d M Y') }} · {{ $log->creator?->name }}</dd>
            @if($log->started_on)<dt class="col-4">{{ __('Started') }}</dt><dd class="col-8">{{ $log->started_on->format('d M Y') }}</dd>@endif
            @if($log->completed_on)<dt class="col-4">{{ __('Completed') }}</dt><dd class="col-8">{{ $log->completed_on->format('d M Y') }}</dd>@endif
            <dt class="col-4">{{ __('Assigned to') }}</dt><dd class="col-8">{{ $log->employee?->name ?? '—' }}</dd>
            <dt class="col-4">{{ __('Supplier / engineer') }}</dt><dd class="col-8">{{ $log->vendor?->name ?? '—' }}</dd>
            @if($log->status === 'completed')
            <dt class="col-4">{{ __('Cost') }}</dt><dd class="col-8">৳{{ number_format($log->cost, 2) }} <span class="text-body-secondary">({{ __('not posted to the books') }})</span></dd>
            @if($log->reference)<dt class="col-4">{{ __('Reference') }}</dt><dd class="col-8">{{ $log->reference }}</dd>@endif
            @endif
            @if($log->outcome)<dt class="col-4">{{ $log->status === 'cancelled' ? __('Cancelled because') : __('Outcome') }}</dt><dd class="col-8">{{ $log->outcome }}</dd>@endif
            @if($log->condition_after)<dt class="col-4">{{ __('Condition after') }}</dt><dd class="col-8">{{ __(ucfirst($log->condition_after)) }}</dd>@endif
        </dl>
    </div></div></div>
    @if($canUpdate && $log->isOpen())
    <div class="col-lg-5">
        @if($log->status === 'open')<form method="POST" action="{{ route('branch.assets.maintenance.orders.start', $log) }}" class="card mb-3">@csrf<div class="card-body"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-play-fill me-1"></i>{{ __('Start work') }}</button></div></form>@endif
        <form method="POST" action="{{ route('branch.assets.maintenance.orders.complete', $log) }}" class="card mb-3">@csrf
            <div class="card-header bg-transparent fw-semibold">{{ __('Complete') }}</div>
            <div class="card-body row g-2">
                <x-form.input name="completed_on" type="date" :label="__('Done on')" :value="now()->toDateString()" required col="col-6" />
                <x-form.input name="cost" type="number" step="0.01" min="0" :label="__('Cost (৳)')" col="col-6" />
                <x-form.input name="reference" :label="__('Job card / certificate no.')" maxlength="100" col="col-12" />
                <x-form.select name="vendor_id" :label="__('Supplier / engineer')" :options="$vendors" :value="$log->vendor_id" :placeholder="__('— none —')" col="col-6" />
                <x-form.select name="condition_after" :label="__('Condition after')" col="col-6" :placeholder="__('unchanged')" :options="collect(App\Models\Assets\Asset::CONDITIONS)->mapWithKeys(fn ($c) => [$c => __(ucfirst($c))])->all()" />
                <x-form.input name="outcome" :label="__('Outcome')" maxlength="255" col="col-12" />
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-brand btn-sm">{{ __('Complete') }}</button></div>
        </form>
        <form method="POST" action="{{ route('branch.assets.maintenance.orders.cancel', $log) }}" class="card" data-confirm="{{ __('Cancel this work order?') }}">@csrf
            <div class="card-body"><label class="form-label small" for="cr">{{ __('Cancel this work order') }}</label><input id="cr" name="reason" class="form-control form-control-sm mb-2" required maxlength="255" placeholder="{{ __('Reason') }}"><button class="btn btn-outline-danger btn-sm">{{ __('Cancel') }}</button></div></form>
    </div>
    @endif
</div>
@endsection
