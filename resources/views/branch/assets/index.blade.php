@extends('layouts.branch')
@section('title', __('Assets'))

@section('content')
<x-page-header :title="__('Assets')" :breadcrumbs="[__('Assets') => null]">
    <x-slot:actions>
        @can('assets.create')<a href="{{ route('branch.assets.register.from-stock') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>{{ __('Move from stock') }}</a>
        <a href="{{ route('branch.assets.register.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Register an asset') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

@unless($books['ok'])
<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>
    {{ __('The register does not agree with the accounts: cost ৳:a in the register against ৳:b in Fixed assets; depreciation ৳:c against ৳:d. A manual voucher may have been posted to these accounts.', ['a' => number_format($books['cost'], 2), 'b' => number_format($books['fixed'], 2), 'c' => number_format($books['accumulated'], 2), 'd' => number_format($books['accountAccumulated'], 2)]) }}</div>
@endunless

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Assets')" :value="$count" icon="fa-solid fa-computer" bg="#cffafe" color="#0e7490" :sub="__(':n in use · cost :v', ['n' => $inUse, 'v' => money_bdt($cost)])" :href="route('branch.assets.register.index')" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Book value')" :value="money_bdt($book)" icon="fa-solid fa-scale-balanced" bg="#dcfce7" color="#15803d" :sub="__('depreciation so far :v', ['v' => money_bdt($accumulated)])" :href="route('branch.assets.reports.summary')" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Maintenance overdue')" :value="$overdue" icon="fa-solid fa-screwdriver-wrench" :bg="$overdue ? '#fee2e2' : '#e0e7ff'" :color="$overdue ? '#b91c1c' : '#4338ca'" :sub="__(':n more due within 30 days', ['n' => $dueSoon])" :href="route('branch.assets.maintenance.plans', ['overdue' => 1])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Needs attention')" :value="$underRepair + $missing + $openOrders" icon="fa-solid fa-triangle-exclamation" bg="#fef3c7" color="#b45309" :sub="__(':a under repair · :b missing · :c warranties ending', ['a' => $underRepair, 'b' => $missing, 'c' => $warranty])" :href="route('branch.assets.maintenance.index', ['status' => 'open'])" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card h-100">
        <div class="card-header bg-transparent fw-semibold">{{ __('By category') }}</div>
        @if($byCategory->isEmpty())<x-empty-state icon="pc-display" :title="__('No assets registered yet')" />
        @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Assets') }}</th><th class="text-end">{{ __('Cost (৳)') }}</th><th class="text-end">{{ __('Depreciation (৳)') }}</th><th class="text-end">{{ __('Book value (৳)') }}</th></tr></thead>
            <tbody>@foreach($byCategory as $c)<tr><td><a href="{{ route('branch.assets.register.index', ['category' => $c['id']]) }}" class="text-decoration-none">{{ $c['category'] }}</a>@if($c['kind'] === 'laboratory') <span class="badge text-bg-info">{{ __('Lab') }}</span>@endif</td>
                <td class="text-end">{{ $c['count'] }}</td><td class="text-end">{{ number_format($c['cost'], 2) }}</td><td class="text-end">{{ number_format($c['accumulated'], 2) }}</td><td class="text-end fw-semibold">{{ number_format($c['book'], 2) }}</td></tr>@endforeach</tbody>
            <tfoot><tr class="fw-semibold"><td>{{ __('Total') }}</td><td class="text-end">{{ $count }}</td><td class="text-end">{{ number_format($cost, 2) }}</td><td class="text-end">{{ number_format($accumulated, 2) }}</td><td class="text-end">{{ number_format($book, 2) }}</td></tr></tfoot></table></div>@endif
    </div></div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold">{{ __('Maintenance due') }}</div>
            @if($duePlans->isEmpty())<x-empty-state icon="check2-circle" :title="__('Nothing is due in the next 30 days')" />
            @else<ul class="list-group list-group-flush small">@foreach($duePlans as $p)
                <li class="list-group-item d-flex justify-content-between"><span><a href="{{ route('branch.assets.register.show', $p->asset_id) }}" class="text-decoration-none">{{ $p->asset?->displayName() }}</a><div class="text-body-secondary">{{ $p->title }}</div></span>
                    <span class="text-end @if($p->isOverdue()) text-danger fw-semibold @endif">{{ $p->next_due_on->format('d M Y') }}<div class="small">@if($p->isOverdue()){{ __(':n day(s) overdue', ['n' => abs($p->daysUntilDue())]) }}@else{{ __('in :n day(s)', ['n' => $p->daysUntilDue()]) }}@endif</div></span></li>@endforeach</ul>@endif
        </div>
        <div class="card">
            <div class="card-header bg-transparent fw-semibold">{{ __('Warranties ending soon') }}</div>
            @if($expiring->isEmpty())<x-empty-state icon="shield-check" :title="__('No warranty ends in the next 60 days')" />
            @else<ul class="list-group list-group-flush small">@foreach($expiring as $a)
                <li class="list-group-item d-flex justify-content-between"><a href="{{ route('branch.assets.register.show', $a) }}" class="text-decoration-none">{{ $a->displayName() }}<div class="text-body-secondary">{{ $a->code }}</div></a><span>{{ $a->warranty_expires_on->format('d M Y') }}</span></li>@endforeach</ul>@endif
        </div>
    </div>
</div>
@endsection
