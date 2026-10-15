@extends('layouts.branch')
@section('title', $adjustment->adjustment_no)

@section('content')
<x-page-header :title="$adjustment->adjustment_no" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Stock adjustments') => route('branch.inventory.adjustments.index'), $adjustment->adjustment_no => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0 small">
        <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Change') }}</th><th class="text-end">{{ __('Value (৳)') }}</th></tr></thead>
        <tbody>@foreach($adjustment->items as $l)<tr><td>{{ $l->item?->name }}<div class="text-body-secondary">{{ $l->item?->code }}</div></td>
            <td class="text-end fw-semibold @if($l->quantity < 0) text-danger @else text-success @endif">{{ $l->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($l->quantity, 3, '.', ''), '0'), '.') }} {{ $l->item?->unit }}</td><td class="text-end">{{ number_format($l->amount, 2) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end">{{ __('Net value') }}</td><td class="text-end">{{ number_format($adjustment->net_value, 2) }}</td></tr></tfoot></table></div></div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small"><div class="mb-2"><x-inv-status :status="$adjustment->status" /></div>
            <dl class="row mb-0"><dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $adjustment->date->format('d M Y') }}</dd>
                <dt class="col-5">{{ __('Reason') }}</dt><dd class="col-7">{{ __(ucfirst($adjustment->reason)) }}</dd>
                <dt class="col-5">{{ __('Posted by') }}</dt><dd class="col-7">{{ $adjustment->creator?->name }}</dd>
                @if($adjustment->audit)<dt class="col-5">{{ __('Audit') }}</dt><dd class="col-7"><a href="{{ route('branch.inventory.audits.show', $adjustment->audit) }}">{{ $adjustment->audit->audit_no }}</a></dd>@endif
                @if($adjustment->note)<dt class="col-5">{{ __('Note') }}</dt><dd class="col-7">{{ $adjustment->note }}</dd>@endif</dl></div></div>
        @if($adjustment->status === 'posted')@can('inventory.approve')
        <form method="POST" action="{{ route('branch.inventory.adjustments.reverse', $adjustment) }}" class="card" data-confirm="{{ __('Reverse this adjustment?') }}">@csrf
            <div class="card-body"><label class="form-label small" for="rv">{{ __('Reverse this adjustment') }}</label><input id="rv" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason') }}" required maxlength="200"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reverse') }}</button></div></form>
        @endcan @endif
    </div>
</div>
@endsection
