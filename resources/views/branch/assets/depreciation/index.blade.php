@extends('layouts.branch')
@section('title', __('Depreciation'))

@section('content')
<x-page-header :title="__('Depreciation')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Depreciation') => null]">
    <x-slot:actions><a href="{{ route('branch.assets.reports.depreciation') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart me-1"></i>{{ __('Depreciation report') }}</a></x-slot:actions>
</x-page-header>
<div class="alert alert-light border small">{{ __('Each month an asset is charged from the month it enters service. A run charges every month an asset has missed. One entry per month is posted: Dr Depreciation, Cr Accumulated depreciation, dated the last day of the month.') }}</div>
<div class="card mb-3">
    <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
        <div class="col-auto"><label class="form-label small mb-0" for="period">{{ __('Charge up to (month)') }}</label><input id="period" type="month" name="period" value="{{ $period }}" max="{{ now()->format('Y-m') }}" class="form-control form-control-sm"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">{{ __('Preview') }}</button></div>
        <div class="col text-md-end small text-body-secondary">{{ __(':n assets · ৳:t', ['n' => $preview->count(), 't' => number_format($preview->sum('total'), 2)]) }}</div>
    </form></div>
    @if($preview->isEmpty())<x-empty-state icon="check2-circle" :title="__('Everything is depreciated up to :p', ['p' => $period])" />
    @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
        <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Months to charge') }}</th><th class="text-end">{{ __('Charge (৳)') }}</th><th class="text-end">{{ __('Book value after (৳)') }}</th></tr></thead>
        <tbody>@foreach($preview as $r)<tr><td><a href="{{ route('branch.assets.register.show', $r['asset']) }}" class="text-decoration-none">{{ $r['asset']->displayName() }}</a><div class="text-body-secondary">{{ $r['asset']->code }} · {{ $r['asset']->category?->name }}</div></td>
            <td>{{ $r['months'][0]['period'] }}@if(count($r['months']) > 1) → {{ end($r['months'])['period'] }} ({{ count($r['months']) }})@endif</td>
            <td class="text-end">{{ number_format($r['total'], 2) }}</td><td class="text-end">{{ number_format(end($r['months'])['book_value'], 2) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end">{{ __('Total') }}</td><td class="text-end">{{ number_format($preview->sum('total'), 2) }}</td><td></td></tr></tfoot></table></div>
        @can('assets.approve')<form method="POST" action="{{ route('branch.assets.depreciation.run') }}" class="card-body border-top" data-confirm="{{ __('Post depreciation up to :p?', ['p' => $period]) }}">@csrf <input type="hidden" name="period" value="{{ $period }}">
            <button class="btn btn-brand"><i class="bi bi-check2-circle me-1"></i>{{ __('Post depreciation up to :p', ['p' => $period]) }}</button></form>@endcan
    @endif
</div>
<div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Runs') }}</div>
    @if($runs->isEmpty())<x-empty-state icon="clock-history" :title="__('Nothing posted yet')" />
    @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
        <thead><tr><th>{{ __('Run') }}</th><th>{{ __('Up to') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Assets') }}</th><th class="text-end">{{ __('Total (৳)') }}</th><th>{{ __('Status') }}</th><th>{{ __('By') }}</th></tr></thead>
        <tbody>@foreach($runs as $r)<tr><td><a href="{{ route('branch.assets.depreciation.show', $r) }}" class="fw-semibold text-decoration-none">{{ $r->run_no }}</a>@if($r->kind === 'disposal')<div class="text-body-secondary">{{ __('before a disposal') }}</div>@endif</td><td>{{ $r->period }}</td><td>{{ $r->date->format('d M Y') }}</td><td class="text-end">{{ $r->asset_count }}</td><td class="text-end">{{ number_format($r->total, 2) }}</td><td><x-inv-status :status="$r->status" /></td><td>{{ $r->runner?->name }}</td></tr>@endforeach</tbody></table></div>
        <div class="card-body">{{ $runs->links() }}</div>@endif
</div>
@endsection
