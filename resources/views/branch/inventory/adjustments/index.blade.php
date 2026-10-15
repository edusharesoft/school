@extends('layouts.branch')
@section('title', __('Stock adjustments'))

@section('content')
<x-page-header :title="__('Stock adjustments')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Stock adjustments') => null]">
    <x-slot:actions>@can('inventory.approve')<a href="{{ route('branch.inventory.adjustments.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New adjustment') }}</a>@endcan</x-slot:actions>
</x-page-header>
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="d-flex gap-2"><select name="reason" class="form-select form-select-sm" style="max-width:240px" aria-label="{{ __('Reason') }}"><option value="">{{ __('All reasons') }}</option>@foreach(App\Models\Inventory\StockAdjustment::REASONS as $r)<option value="{{ $r }}" @selected(request('reason') === $r)>{{ __(ucfirst($r)) }}</option>@endforeach</select><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></form></div>
    @if($adjustments->isEmpty())<x-empty-state icon="sliders" :title="__('No stock adjustments')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Adjustment') }}</th><th>{{ __('Date') }}</th><th>{{ __('Reason') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end">{{ __('Net value (৳)') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>@foreach($adjustments as $a)<tr><td><a href="{{ route('branch.inventory.adjustments.show', $a) }}" class="fw-semibold text-decoration-none">{{ $a->adjustment_no }}</a><div class="text-body-secondary">{{ $a->creator?->name }}</div></td>
            <td class="text-nowrap">{{ $a->date->format('d M Y') }}</td><td>{{ __(ucfirst($a->reason)) }}<div class="text-body-secondary">{{ $a->note }}</div></td><td class="text-end">{{ $a->items_count }}</td>
            <td class="text-end fw-semibold @if($a->net_value < 0) text-danger @elseif($a->net_value > 0) text-success @endif">{{ number_format($a->net_value, 2) }}</td><td><x-inv-status :status="$a->status" /></td></tr>@endforeach</tbody></table></div>
    <div class="card-body">{{ $adjustments->links() }}</div>@endif
</div>
@endsection
