@extends('layouts.branch')
@section('title', __('Purchase requests'))

@section('content')
<x-page-header :title="__('Purchase requests')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Purchase requests') => null]">
    <x-slot:actions>@can('inventory.create')<a href="{{ route('branch.inventory.requisitions.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New request') }}</a>@endcan</x-slot:actions>
</x-page-header>
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="row g-2">
        <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>@foreach(App\Models\Inventory\Requisition::STATUSES as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ __(ucfirst($st)) }}</option>@endforeach</select></div>
        <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Number or purpose') }}" aria-label="{{ __('Search') }}"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    </form></div>
    @if($requisitions->isEmpty())<x-empty-state icon="clipboard" :title="__('No purchase requests')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Request') }}</th><th>{{ __('Date') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Department') }}</th><th class="text-end">{{ __('Lines') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>@foreach($requisitions as $r)<tr>
            <td><a href="{{ route('branch.inventory.requisitions.show', $r) }}" class="fw-semibold text-decoration-none">{{ $r->requisition_no }}</a><div class="text-body-secondary">{{ $r->requester?->name }}</div></td>
            <td class="text-nowrap">{{ $r->date->format('d M Y') }}</td><td>{{ $r->purpose }}</td><td>{{ $r->department?->name }}</td><td class="text-end">{{ $r->items_count }}</td>
            <td><x-inv-status :status="$r->status" /></td></tr>@endforeach</tbody></table></div>
    <div class="card-body">{{ $requisitions->links() }}</div>@endif
</div>
@endsection
