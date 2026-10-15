@extends('layouts.branch')
@section('title', $issue->issue_no)

@section('content')
<x-page-header :title="$issue->issue_no" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Stock issues') => route('branch.inventory.issues.index'), $issue->issue_no => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0 small">
        <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Value (৳)') }}</th></tr></thead>
        <tbody>@foreach($issue->items as $l)<tr><td>{{ $l->item?->name }}<div class="text-body-secondary">{{ $l->item?->code }}</div></td><td class="text-end">{{ rtrim(rtrim(number_format($l->quantity, 3, '.', ''), '0'), '.') }} {{ $l->item?->unit }}</td><td class="text-end">{{ number_format($l->amount, 2) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end">{{ __('Total') }}</td><td class="text-end">{{ number_format($issue->total, 2) }}</td></tr></tfoot></table></div></div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small"><div class="mb-2"><x-inv-status :status="$issue->status" /></div>
            <dl class="row mb-0"><dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $issue->date->format('d M Y') }}</dd>
                <dt class="col-5">{{ __('Issued to') }}</dt><dd class="col-7">{{ $issue->recipientLabel() }}</dd>
                <dt class="col-5">{{ __('Issued by') }}</dt><dd class="col-7">{{ $issue->issuer?->name }}</dd>
                @if($issue->purpose)<dt class="col-5">{{ __('Purpose') }}</dt><dd class="col-7">{{ $issue->purpose }}</dd>@endif</dl></div></div>
        @if($issue->status === 'issued')@can('inventory.approve')
        <form method="POST" action="{{ route('branch.inventory.issues.reverse', $issue) }}" class="card" data-confirm="{{ __('Reverse this issue? The goods go back into stock.') }}">@csrf
            <div class="card-body"><label class="form-label small" for="rv">{{ __('Reverse (goods returned)') }}</label><input id="rv" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason') }}" required maxlength="200"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reverse') }}</button></div></form>
        @endcan @endif
    </div>
</div>
@endsection
