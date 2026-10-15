@extends('layouts.branch')
@section('title', __('Stock issues'))

@section('content')
<x-page-header :title="__('Stock issues')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Stock issues') => null]">
    <x-slot:actions>@can('inventory.create')<a href="{{ route('branch.inventory.issues.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-dolly me-1"></i>{{ __('Issue stock') }}</a>@endcan</x-slot:actions>
</x-page-header>
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-md-3"><select name="department_id" class="form-select form-select-sm" aria-label="{{ __('Department') }}"><option value="">{{ __('All departments') }}</option>@foreach($departments as $id => $name)<option value="{{ $id }}" @selected((int) request('department_id') === $id)>{{ $name }}</option>@endforeach</select></div>
        <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Number or recipient') }}" aria-label="{{ __('Search') }}"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    </form></div>
    @if($issues->isEmpty())<x-empty-state icon="dolly" :title="__('No stock issued in this period')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Issue') }}</th><th>{{ __('Date') }}</th><th>{{ __('Issued to') }}</th><th>{{ __('Purpose') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end">{{ __('Value (৳)') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>@foreach($issues as $i)<tr><td><a href="{{ route('branch.inventory.issues.show', $i) }}" class="fw-semibold text-decoration-none">{{ $i->issue_no }}</a><div class="text-body-secondary">{{ $i->issuer?->name }}</div></td>
            <td class="text-nowrap">{{ $i->date->format('d M Y') }}</td><td>{{ $i->recipientLabel() }}</td><td>{{ $i->purpose }}</td><td class="text-end">{{ $i->items_count }}</td><td class="text-end">{{ number_format($i->total, 2) }}</td><td><x-inv-status :status="$i->status" /></td></tr>@endforeach</tbody></table></div>
    <div class="card-body">{{ $issues->links() }}</div>@endif
</div>
@endsection
