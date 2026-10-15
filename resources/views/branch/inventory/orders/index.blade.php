@extends('layouts.branch')
@section('title', __('Purchase orders'))

@section('content')
<x-page-header :title="__('Purchase orders')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Purchase orders') => null]">
    <x-slot:actions>@can('inventory.create')<a href="{{ route('branch.inventory.orders.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New order') }}</a>@endcan</x-slot:actions>
</x-page-header>
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="row g-2">
        <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>@foreach(App\Models\Inventory\PurchaseOrder::STATUSES as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ __(ucfirst($st)) }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="vendor_id" class="form-select form-select-sm" aria-label="{{ __('Supplier') }}"><option value="">{{ __('All suppliers') }}</option>@foreach($vendors as $id => $name)<option value="{{ $id }}" @selected((int) request('vendor_id') === $id)>{{ $name }}</option>@endforeach</select></div>
        <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Order number') }}" aria-label="{{ __('Search') }}"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    </form></div>
    @if($orders->isEmpty())<x-empty-state icon="file-earmark-text" :title="__('No purchase orders')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Expected') }}</th><th class="text-end">{{ __('Total (৳)') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>@foreach($orders as $o)<tr><td><a href="{{ route('branch.inventory.orders.show', $o) }}" class="fw-semibold text-decoration-none">{{ $o->po_no }}</a></td>
            <td class="text-nowrap">{{ $o->order_date->format('d M Y') }}</td><td>{{ $o->vendor?->name }}</td><td class="text-nowrap">{{ $o->expected_on?->format('d M Y') ?? '—' }}</td>
            <td class="text-end">{{ number_format($o->total, 2) }}</td><td><x-inv-status :status="$o->status" /></td></tr>@endforeach</tbody></table></div>
    <div class="card-body">{{ $orders->links() }}</div>@endif
</div>
@endsection
