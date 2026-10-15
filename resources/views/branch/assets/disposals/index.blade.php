@extends('layouts.branch')
@section('title', __('Disposals'))

@section('content')
<x-page-header :title="__('Disposals')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Disposals') => null]" />
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Disposed')" :value="$rows->count()" icon="fa-solid fa-box-archive" bg="#e2e8f0" color="#475569" :sub="__('book value :v', ['v' => money_bdt($bookValue)])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Money received')" :value="money_bdt($proceeds)" icon="fa-solid fa-hand-holding-dollar" bg="#dcfce7" color="#15803d" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Gains')" :value="money_bdt($gain)" icon="fa-solid fa-arrow-trend-up" bg="#dbeafe" color="#1d4ed8" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Losses')" :value="money_bdt($loss)" icon="fa-solid fa-arrow-trend-down" bg="#fee2e2" color="#b91c1c" /></div>
</div>
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
        <div class="col-auto"><label class="form-label small mb-0" for="f">{{ __('From') }}</label><input id="f" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-auto"><label class="form-label small mb-0" for="t">{{ __('To') }}</label><input id="t" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    </form></div>
    @if($rows->isEmpty())<x-empty-state icon="box-arrow-up-right" :title="__('No disposals in this period')" :message="__('Dispose of an asset from its page in the register.')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Asset') }}</th><th>{{ __('How') }}</th><th class="text-end">{{ __('Book value (৳)') }}</th><th class="text-end">{{ __('Received (৳)') }}</th><th class="text-end">{{ __('Gain / loss (৳)') }}</th></tr></thead>
        <tbody>@foreach($rows as $d)<tr><td><a href="{{ route('branch.assets.disposals.show', $d) }}" class="fw-semibold text-decoration-none">{{ $d->disposal_no }}</a></td><td class="text-nowrap">{{ $d->date->format('d M Y') }}</td>
            <td>{{ $d->asset?->displayName() }}<div class="text-body-secondary">{{ $d->asset?->code }}</div></td><td>{{ __(ucfirst($d->type)) }}</td>
            <td class="text-end">{{ number_format($d->book_value, 2) }}</td><td class="text-end">{{ number_format($d->proceeds, 2) }}</td>
            <td class="text-end fw-semibold @if($d->gain_loss < 0) text-danger @elseif($d->gain_loss > 0) text-success @endif">{{ $d->gain_loss > 0 ? '+' : '' }}{{ number_format($d->gain_loss, 2) }}</td></tr>@endforeach</tbody></table></div>@endif
</div>
@endsection
