@extends('layouts.branch')
@section('title', $order->po_no)

@section('content')
<x-page-header :title="$order->po_no" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Purchase orders') => route('branch.inventory.orders.index'), $order->po_no => null]">
    <x-slot:actions>
        <a href="{{ route('branch.inventory.orders.print', $order) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</a>
        @if($order->isReceivable())@can('inventory.create')<a href="{{ route('branch.inventory.receipts.create', ['order' => $order->id]) }}" class="btn btn-brand btn-sm"><i class="bi bi-truck me-1"></i>{{ __('Receive goods') }}</a>@endcan @endif
    </x-slot:actions>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0 small">
        <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Ordered') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Unit cost (৳)') }}</th><th class="text-end">{{ __('Amount (৳)') }}</th></tr></thead>
        <tbody>@foreach($order->items as $l)<tr><td>{{ $l->item?->name }}<div class="text-body-secondary">{{ $l->item?->code }}</div></td>
            <td class="text-end">{{ rtrim(rtrim(number_format($l->quantity, 3, '.', ''), '0'), '.') }} {{ $l->item?->unit }}</td>
            <td class="text-end @if($l->pending() > 0 && $order->status === 'partial') text-warning-emphasis @endif">{{ rtrim(rtrim(number_format($l->received_quantity, 3, '.', ''), '0'), '.') ?: '0' }}</td>
            <td class="text-end">{{ number_format($l->unit_cost, 2) }}</td><td class="text-end">{{ number_format($l->quantity * $l->unit_cost, 2) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="fw-semibold"><td colspan="4" class="text-end">{{ __('Total') }}</td><td class="text-end">{{ number_format($order->total, 2) }}</td></tr></tfoot></table></div></div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small">
            <div class="mb-2"><x-inv-status :status="$order->status" /></div>
            <dl class="row mb-0">
                <dt class="col-5">{{ __('Supplier') }}</dt><dd class="col-7">{{ $order->vendor?->name }}<br>{{ $order->vendor?->phone }}</dd>
                <dt class="col-5">{{ __('Order date') }}</dt><dd class="col-7">{{ $order->order_date->format('d M Y') }}</dd>
                <dt class="col-5">{{ __('Expected on') }}</dt><dd class="col-7">{{ $order->expected_on?->format('d M Y') ?? '—' }}</dd>
                <dt class="col-5">{{ __('Prepared by') }}</dt><dd class="col-7">{{ $order->creator?->name }}</dd>
                @if($order->requisition)<dt class="col-5">{{ __('Request') }}</dt><dd class="col-7"><a href="{{ route('branch.inventory.requisitions.show', $order->requisition) }}">{{ $order->requisition->requisition_no }}</a></dd>@endif
                @if($order->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $order->notes }}</dd>@endif
            </dl>
            @foreach($order->receipts as $r)<div class="mt-2"><a href="{{ route('branch.inventory.receipts.show', $r) }}" class="text-decoration-none"><i class="bi bi-truck me-1"></i>{{ $r->grn_no }}</a> <x-inv-status :status="$r->status" /></div>@endforeach
        </div></div>
        @if($order->status === 'draft')
            @can('inventory.approve')
                @if((int) $order->created_by !== (int) auth()->id())<form method="POST" action="{{ route('branch.inventory.orders.approve', $order) }}" class="mb-2">@csrf<button class="btn btn-success btn-sm"><i class="bi bi-check2 me-1"></i>{{ __('Approve order') }}</button></form>
                @else<div class="alert alert-info small">{{ __('Someone else has to approve an order you prepared.') }}</div>@endif
            @endcan
        @endif
        @if(in_array($order->status, ['draft', 'approved'], true) && $order->receipts->where('status', 'posted')->isEmpty())@can('inventory.update')
            <form method="POST" action="{{ route('branch.inventory.orders.cancel', $order) }}" data-confirm="{{ __('Cancel this order?') }}">@csrf<button class="btn btn-outline-secondary btn-sm">{{ __('Cancel order') }}</button></form>
        @endcan @endif
    </div>
</div>
@endsection
