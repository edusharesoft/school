@extends('layouts.branch')
@section('title', __('Asset reports'))

@section('content')
<x-page-header :title="__('Asset reports')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Reports') => null]" />
@include('branch.assets.reports._nav')
<div class="card mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('Value by category') }}</div>
    @if($byCategory->isEmpty())<x-empty-state icon="pc-display" :title="__('No assets yet')" />
    @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
        <thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Assets') }}</th><th class="text-end">{{ __('Cost (৳)') }}</th><th class="text-end">{{ __('Depreciation (৳)') }}</th><th class="text-end">{{ __('Book value (৳)') }}</th></tr></thead>
        <tbody>@foreach($byCategory as $c)<tr><td><a href="{{ route('branch.assets.register.index', ['category' => $c['id']]) }}" class="text-decoration-none">{{ $c['category'] }}</a></td><td class="text-end">{{ $c['count'] }}</td><td class="text-end">{{ number_format($c['cost'], 2) }}</td><td class="text-end">{{ number_format($c['accumulated'], 2) }}</td><td class="text-end fw-semibold">{{ number_format($c['book'], 2) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="fw-semibold"><td>{{ __('Total') }}</td><td class="text-end">{{ $byCategory->sum('count') }}</td><td class="text-end">{{ number_format($byCategory->sum('cost'), 2) }}</td><td class="text-end">{{ number_format($byCategory->sum('accumulated'), 2) }}</td><td class="text-end">{{ number_format($byCategory->sum('book'), 2) }}</td></tr></tfoot></table></div>@endif
</div>
<div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Register against the accounts') }}</div>
    <div class="table-responsive"><table class="table table-sm mb-0 small">
        <thead><tr><th></th><th class="text-end">{{ __('Register (৳)') }}</th><th class="text-end">{{ __('Accounts (৳)') }}</th><th></th></tr></thead>
        <tbody>
            <tr><td>{{ __('Fixed assets at cost') }}</td><td class="text-end">{{ number_format($books['cost'], 2) }}</td><td class="text-end">{{ number_format($books['fixed'], 2) }}</td><td>@if(abs($books['cost'] - $books['fixed']) < 0.005)<i class="bi bi-check-circle text-success"></i>@else<i class="bi bi-x-circle text-danger"></i>@endif</td></tr>
            <tr><td>{{ __('Accumulated depreciation') }}</td><td class="text-end">{{ number_format($books['accumulated'], 2) }}</td><td class="text-end">{{ number_format($books['accountAccumulated'], 2) }}</td><td>@if(abs($books['accumulated'] - $books['accountAccumulated']) < 0.005)<i class="bi bi-check-circle text-success"></i>@else<i class="bi bi-x-circle text-danger"></i>@endif</td></tr>
        </tbody></table></div>
    <div class="card-body small text-body-secondary">{{ __('A difference means a manual voucher touched these accounts; the register is the detailed record.') }}</div>
</div>
@endsection
