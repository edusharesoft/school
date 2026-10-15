@extends('layouts.branch')
@section('title', __('Student seats'))

@section('content')
<x-page-header :title="__('Student seats')" :breadcrumbs="[__('Transport') => route('branch.transport.index'), __('Student seats') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="active">{{ __('Riding now') }}</option><option value="ended" @selected($status === 'ended')>{{ __('Ended') }}</option></select></div>
            <div class="col-md-3"><select name="route_id" class="form-select form-select-sm" aria-label="{{ __('Route') }}"><option value="">{{ __('All routes') }}</option>@foreach($routes as $r)<option value="{{ $r->id }}" @selected((int) request('route_id') === $r->id)>{{ $r->code }}</option>@endforeach</select></div>
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Student') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form></div>
        @if($allocations->isEmpty())<x-empty-state icon="person-check" :title="__('No seats here')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Route / stop') }}</th><th>{{ __('Since') }}</th><th class="text-end">{{ __('Monthly fee') }}</th><th></th></tr></thead>
            <tbody>@foreach($allocations as $a)
                <tr><td>{{ $a->student?->name }}<div class="text-body-secondary">{{ $a->student?->student_code }}</div></td><td>{{ $a->route?->code }} · {{ $a->stop?->name }}</td>
                    <td>{{ $a->starts_on->format('d M Y') }}@if($a->ends_on)<div class="text-body-secondary">→ {{ $a->ends_on->format('d M Y') }}</div>@endif</td>
                    <td class="text-end">
                        @if($a->status === 'active' && auth()->user()->can('transport.update'))
                            <form method="POST" action="{{ route('branch.transport.allocations.update', $a) }}" class="d-inline-flex gap-1">@csrf @method('PUT')<input type="number" step="0.01" min="0" name="monthly_fee" value="{{ $a->monthly_fee }}" class="form-control form-control-sm" style="width:90px" aria-label="{{ __('Monthly fee') }}"><button class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Save') }}"><i class="bi bi-check-lg"></i></button></form>
                        @else ৳{{ number_format((float) $a->monthly_fee, 0) }} @endif
                    </td>
                    <td class="text-end text-nowrap">@if($a->status === 'active' && auth()->user()->can('transport.update'))
                        <form method="POST" action="{{ route('branch.transport.allocations.transfer', $a) }}" class="d-inline-flex gap-1">@csrf
                            <select name="transport_stop_id" class="form-select form-select-sm" style="width:150px" aria-label="{{ __('New stop') }}">@foreach($routes as $r)<optgroup label="{{ $r->code }}">@foreach($r->stops as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</optgroup>@endforeach</select>
                            <button class="btn btn-sm btn-outline-primary">{{ __('Move') }}</button></form>
                        <form method="POST" action="{{ route('branch.transport.allocations.end', $a) }}" class="d-inline" data-confirm="{{ __('End this seat today?') }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('End') }}</button></form>
                    @endif</td></tr>
            @endforeach</tbody></table></div><div class="card-footer bg-transparent">{{ $allocations->links() }}</div>@endif
    </div></div>
    @can('transport.create')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.transport.allocations.store') }}" class="card" x-data="{ stop: '' }">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Allocate a seat') }}</div>
        <div class="card-body row g-3">
            <x-student-picker col="col-12" />
            <div class="col-12"><label class="form-label" for="transport_stop_id">{{ __('Route & stop') }} <span class="text-danger">*</span></label>
                <select id="transport_stop_id" name="transport_stop_id" class="form-select @error('transport_stop_id') is-invalid @enderror" required x-model="stop">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach($routes as $r)<optgroup label="{{ $r->code }} — {{ $r->name }} ({{ $r->seats }}{{ $r->vehicle ? '/'.$r->vehicle->capacity : '' }})">@foreach($r->stops as $s)<option value="{{ $s->id }}">{{ $s->name }} — ৳{{ number_format($s->fee(), 0) }}</option>@endforeach</optgroup>@endforeach
                </select>@error('transport_stop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <x-form.input name="monthly_fee" type="number" step="0.01" min="0" :label="__('Monthly fee (blank = stop fee)')" col="col-12" />
            <x-form.input name="starts_on" type="date" :label="__('Starts on')" :value="now()->toDateString()" col="col-12" />
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Allocate') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
