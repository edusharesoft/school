@extends('layouts.branch')
@section('title', $requisition->requisition_no)

@section('content')
<x-page-header :title="$requisition->requisition_no" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Purchase requests') => route('branch.inventory.requisitions.index'), $requisition->requisition_no => null]">
    <x-slot:actions>
        @if($requisition->status === 'approved')@can('inventory.create')<a href="{{ route('branch.inventory.orders.create', ['requisition' => $requisition->id]) }}" class="btn btn-brand btn-sm"><i class="bi bi-file-earmark-plus me-1"></i>{{ __('Create purchase order') }}</a>@endcan @endif
    </x-slot:actions>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        <div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Estimated cost (৳)') }}</th><th class="text-end">{{ __('Amount (৳)') }}</th></tr></thead>
            <tbody>@foreach($requisition->items as $l)<tr><td>{{ $l->item?->name }}<div class="text-body-secondary">{{ $l->item?->code }}</div></td>
                <td class="text-end">{{ rtrim(rtrim(number_format($l->quantity, 3, '.', ''), '0'), '.') }} {{ $l->item?->unit }}</td><td class="text-end">{{ number_format($l->estimated_unit_cost, 2) }}</td><td class="text-end">{{ number_format($l->quantity * $l->estimated_unit_cost, 2) }}</td></tr>@endforeach</tbody>
            <tfoot><tr class="fw-semibold"><td colspan="3" class="text-end">{{ __('Estimated total') }}</td><td class="text-end">{{ number_format($requisition->estimatedTotal(), 2) }}</td></tr></tfoot></table></div>
    </div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small">
            <div class="mb-2"><x-inv-status :status="$requisition->status" /></div>
            <dl class="row mb-0">
                <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $requisition->date->format('d M Y') }}</dd>
                <dt class="col-5">{{ __('Needed by') }}</dt><dd class="col-7">{{ $requisition->needed_by?->format('d M Y') ?? '—' }}</dd>
                <dt class="col-5">{{ __('Requested by') }}</dt><dd class="col-7">{{ $requisition->requester?->name }}</dd>
                <dt class="col-5">{{ __('Department') }}</dt><dd class="col-7">{{ $requisition->department?->name ?? '—' }}</dd>
                <dt class="col-5">{{ __('Purpose') }}</dt><dd class="col-7">{{ $requisition->purpose }}</dd>
                @if($requisition->approver)<dt class="col-5">{{ __('Reviewed by') }}</dt><dd class="col-7">{{ $requisition->approver->name }}<br>{{ $requisition->approved_at?->format('d M Y') }}</dd>@endif
                @if($requisition->review_note)<dt class="col-5">{{ __('Note') }}</dt><dd class="col-7">{{ $requisition->review_note }}</dd>@endif
            </dl>
            @foreach($orders as $o)<div class="mt-2"><a href="{{ route('branch.inventory.orders.show', $o) }}" class="text-decoration-none"><i class="bi bi-file-earmark-text me-1"></i>{{ $o->po_no }}</a> <x-inv-status :status="$o->status" /></div>@endforeach
        </div></div>
        @if($requisition->status === 'pending')
            @can('inventory.approve')
            @if((int) $requisition->requested_by !== (int) auth()->id())
            <div class="card mb-3"><div class="card-body">
                <form method="POST" action="{{ route('branch.inventory.requisitions.approve', $requisition) }}" class="mb-3">@csrf
                    <label class="form-label small" for="rv_note">{{ __('Note (optional)') }}</label><input id="rv_note" name="review_note" class="form-control form-control-sm mb-2" maxlength="255">
                    <button class="btn btn-success btn-sm"><i class="bi bi-check2 me-1"></i>{{ __('Approve') }}</button></form>
                <form method="POST" action="{{ route('branch.inventory.requisitions.reject', $requisition) }}">@csrf
                    <label class="form-label small" for="rj_note">{{ __('Reason for rejecting') }}</label><input id="rj_note" name="review_note" class="form-control form-control-sm mb-2" maxlength="255" required>
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg me-1"></i>{{ __('Reject') }}</button></form>
            </div></div>
            @else<div class="alert alert-info small">{{ __('Someone else has to approve your own request.') }}</div>@endif
            @endcan
        @endif
        @if(in_array($requisition->status, ['pending', 'approved'], true) && (auth()->user()->can('inventory.approve') || ((int) $requisition->requested_by === (int) auth()->id() && auth()->user()->can('inventory.create'))))
            <form method="POST" action="{{ route('branch.inventory.requisitions.cancel', $requisition) }}" data-confirm="{{ __('Cancel this request?') }}">@csrf<button class="btn btn-outline-secondary btn-sm">{{ __('Cancel request') }}</button></form>
        @endif
    </div>
</div>
@endsection
