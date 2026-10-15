@extends('layouts.central')
@section('title', __('Branch-wise inventory'))

@section('content')
<x-page-header :title="__('Branch-wise inventory')" :breadcrumbs="[__('Institution') => null, __('Branch-wise inventory') => null]" />
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Stock value')" :value="money_bdt($total['value'])" icon="fa-solid fa-sack-dollar" bg="#dcfce7" color="#15803d" :sub="__(':n items', ['n' => $total['items']])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Low stock items')" :value="$total['low']" icon="fa-solid fa-triangle-exclamation" bg="#fee2e2" color="#b91c1c" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Open orders')" :value="$total['openOrders']" icon="fa-solid fa-file-invoice" bg="#fef3c7" color="#b45309" :sub="money_bdt($total['openOrderValue'])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Owed to suppliers')" :value="money_bdt($total['due'])" icon="fa-solid fa-file-invoice-dollar" bg="#e0e7ff" color="#4338ca" /></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
    <thead><tr><th>{{ __('Branch') }}</th><th class="text-end">{{ __('Items') }}</th><th class="text-end">{{ __('Stock value (৳)') }}</th><th class="text-end">{{ __('Low stock') }}</th><th class="text-end">{{ __('Open orders') }}</th><th class="text-end">{{ __('Open order value (৳)') }}</th><th class="text-end">{{ __('Bought this year (৳)') }}</th><th class="text-end">{{ __('Owed to suppliers (৳)') }}</th></tr></thead>
    <tbody>@foreach($rows as $r)<tr><td class="fw-semibold">{{ $r['branch']->name }} <span class="text-body-secondary">{{ $r['branch']->code }}</span></td><td class="text-end">{{ $r['items'] }}</td><td class="text-end">{{ number_format($r['value'], 2) }}</td>
        <td class="text-end @if($r['low']) text-danger fw-semibold @endif">{{ $r['low'] }}</td><td class="text-end">{{ $r['openOrders'] }}</td><td class="text-end">{{ number_format($r['openOrderValue'], 2) }}</td><td class="text-end">{{ number_format($r['purchased'], 2) }}</td><td class="text-end @if($r['due'] > 0) text-danger @endif">{{ number_format($r['due'], 2) }}</td></tr>@endforeach</tbody>
    <tfoot><tr class="fw-semibold table-light"><td>{{ __('Total') }}</td><td class="text-end">{{ $total['items'] }}</td><td class="text-end">{{ number_format($total['value'], 2) }}</td><td class="text-end">{{ $total['low'] }}</td><td class="text-end">{{ $total['openOrders'] }}</td><td class="text-end">{{ number_format($total['openOrderValue'], 2) }}</td><td class="text-end">{{ number_format($total['purchased'], 2) }}</td><td class="text-end">{{ number_format($total['due'], 2) }}</td></tr></tfoot></table></div></div>
@endsection
