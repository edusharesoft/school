@extends('layouts.branch')
@section('title', __('Financial years'))

@section('content')
<x-page-header :title="__('Financial years')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Financial years') => null]" />
@include('branch.finance.reports._acc_nav')

<div class="card" style="max-width: 800px">
    <table class="table align-middle mb-0 small">
        <thead><tr><th>{{ __('Year') }}</th><th>{{ __('Period') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($years as $y)
            <tr>
                <td class="fw-semibold">{{ $y->name }}</td>
                <td>{{ $y->starts_on->format('d M Y') }} – {{ $y->ends_on->format('d M Y') }}</td>
                <td>@if($y->is_closed)<span class="badge text-bg-secondary">{{ __('Closed') }}</span> <span class="text-body-secondary">{{ $y->closed_at?->format('d M Y') }}</span>@else<span class="badge text-bg-success">{{ __('Open') }}</span>@endif</td>
                <td class="text-end">
                    @if($y->is_closed ? auth()->user()->can('accounting.reverse') : auth()->user()->can('accounting.post'))
                        <form method="POST" action="{{ route('branch.finance.years.toggle', $y) }}" data-confirm="{{ $y->is_closed ? __('Reopen this financial year?') : __('Close this financial year? No entry can be dated in it afterwards.') }}">@csrf
                            <button class="btn btn-sm {{ $y->is_closed ? 'btn-outline-secondary' : 'btn-outline-danger' }}">{{ $y->is_closed ? __('Reopen') : __('Close year') }}</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="card-footer bg-transparent small text-body-secondary">{{ __('Years are created automatically from the start month in the fee settings. Close a year after the audit; income and expenditure carry into the balance sheet as accumulated surplus.') }}</div>
</div>
@endsection
