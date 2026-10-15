@extends('layouts.central')
@section('title', __('Branch-wise assets'))

@section('content')
<x-page-header :title="__('Branch-wise assets')" :breadcrumbs="[__('Institution') => null, __('Branch-wise assets') => null]" />
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Assets')" :value="$total['count']" icon="fa-solid fa-computer" bg="#cffafe" color="#0e7490" :sub="__('cost :v', ['v' => money_bdt($total['cost'])])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Book value')" :value="money_bdt($total['book'])" icon="fa-solid fa-scale-balanced" bg="#dcfce7" color="#15803d" :sub="__('depreciation :v', ['v' => money_bdt($total['accumulated'])])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Maintenance overdue')" :value="$total['overdue']" icon="fa-solid fa-screwdriver-wrench" bg="#fee2e2" color="#b91c1c" :sub="__(':n under repair', ['n' => $total['repair']])" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Missing')" :value="$total['missing']" icon="fa-solid fa-circle-question" bg="#fef3c7" color="#b45309" :sub="__(':n disposed this year', ['n' => $total['disposed']])" /></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
    <thead><tr><th>{{ __('Branch') }}</th><th class="text-end">{{ __('Assets') }}</th><th class="text-end">{{ __('Cost (৳)') }}</th><th class="text-end">{{ __('Depreciation (৳)') }}</th><th class="text-end">{{ __('Book value (৳)') }}</th><th class="text-end">{{ __('Under repair') }}</th><th class="text-end">{{ __('Missing') }}</th><th class="text-end">{{ __('Maintenance overdue') }}</th><th class="text-end">{{ __('Disposed this year') }}</th><th class="text-end">{{ __('Gain / loss (৳)') }}</th></tr></thead>
    <tbody>@foreach($rows as $r)<tr><td class="fw-semibold">{{ $r['branch']->name }} <span class="text-body-secondary">{{ $r['branch']->code }}</span></td><td class="text-end">{{ $r['count'] }}</td><td class="text-end">{{ number_format($r['cost'], 2) }}</td><td class="text-end">{{ number_format($r['accumulated'], 2) }}</td><td class="text-end">{{ number_format($r['book'], 2) }}</td>
        <td class="text-end">{{ $r['repair'] }}</td><td class="text-end @if($r['missing']) text-danger fw-semibold @endif">{{ $r['missing'] }}</td><td class="text-end @if($r['overdue']) text-danger fw-semibold @endif">{{ $r['overdue'] }}</td><td class="text-end">{{ $r['disposed'] }}</td>
        <td class="text-end @if($r['gainLoss'] < 0) text-danger @elseif($r['gainLoss'] > 0) text-success @endif">{{ number_format($r['gainLoss'], 2) }}</td></tr>@endforeach</tbody>
    <tfoot><tr class="fw-semibold table-light"><td>{{ __('Total') }}</td><td class="text-end">{{ $total['count'] }}</td><td class="text-end">{{ number_format($total['cost'], 2) }}</td><td class="text-end">{{ number_format($total['accumulated'], 2) }}</td><td class="text-end">{{ number_format($total['book'], 2) }}</td><td class="text-end">{{ $total['repair'] }}</td><td class="text-end">{{ $total['missing'] }}</td><td class="text-end">{{ $total['overdue'] }}</td><td class="text-end">{{ $total['disposed'] }}</td><td class="text-end">{{ number_format($total['gainLoss'], 2) }}</td></tr></tfoot></table></div></div>
@endsection
