@extends('layouts.branch')
@section('title', $route->name)

@section('content')
<x-page-header :title="$route->code.' — '.$route->name" :breadcrumbs="[__('Routes') => route('branch.transport.routes.index'), $route->code => null]">
    <x-slot:actions>
        <a href="{{ route('branch.transport.routes.sheet', $route) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>{{ __('Route sheet') }}</a>
        @can('transport.update')<a href="{{ route('branch.transport.routes.edit', $route) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>@endcan
    </x-slot:actions>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4"><div class="card"><div class="card-body small"><dl class="row mb-0">
        <dt class="col-5">{{ __('Vehicle') }}</dt><dd class="col-7">{{ $route->vehicle?->label() ?? '—' }}</dd>
        <dt class="col-5">{{ __('Driver') }}</dt><dd class="col-7">{{ $route->driver?->name ?? '—' }} {{ $route->driver?->phone }}</dd>
        <dt class="col-5">{{ __('Helper') }}</dt><dd class="col-7">{{ $route->helper?->name ?? '—' }} {{ $route->helper?->phone }}</dd>
        <dt class="col-5">{{ __('Seats') }}</dt><dd class="col-7">{{ $riders->count() }}{{ $route->vehicle ? ' / '.$route->vehicle->capacity : '' }} @if($seatsLeft !== null)<span class="text-body-secondary">({{ $seatsLeft }} {{ __('free') }})</span>@endif</dd>
        <dt class="col-5">{{ __('Fee') }}</dt><dd class="col-7">৳{{ number_format((float) $route->monthly_fee, 0) }}</dd>
        <dt class="col-5">{{ __('Morning') }}</dt><dd class="col-7">{{ $route->morning_start ? substr($route->morning_start, 0, 5) : '—' }}</dd>
        <dt class="col-5">{{ __('Afternoon') }}</dt><dd class="col-7">{{ $route->afternoon_start ? substr($route->afternoon_start, 0, 5) : '—' }}</dd>
    </dl>@if($route->description)<hr><p class="mb-0">{{ $route->description }}</p>@endif</div></div></div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold">{{ __('Stops') }}</div>
            <div class="table-responsive"><table class="table align-middle mb-0 small">
                <thead><tr><th>#</th><th>{{ __('Stop') }}</th><th>{{ __('Pickup') }}</th><th>{{ __('Drop') }}</th><th>{{ __('Fee') }}</th><th></th></tr></thead>
                <tbody>@foreach($stops as $s)
                    <tr>
                        <form method="POST" action="{{ route('branch.transport.stops.update', $s) }}" id="stop{{ $s->id }}">@csrf @method('PUT')</form>
                        <td style="width:70px"><input form="stop{{ $s->id }}" type="number" min="1" name="sequence" value="{{ $s->sequence }}" class="form-control form-control-sm" aria-label="{{ __('Order') }}"></td>
                        <td><input form="stop{{ $s->id }}" name="name" value="{{ $s->name }}" class="form-control form-control-sm" required maxlength="120" aria-label="{{ __('Stop') }}"></td>
                        <td><input form="stop{{ $s->id }}" type="time" name="pickup_time" value="{{ $s->pickup_time ? substr($s->pickup_time, 0, 5) : '' }}" class="form-control form-control-sm" aria-label="{{ __('Pickup') }}"></td>
                        <td><input form="stop{{ $s->id }}" type="time" name="drop_time" value="{{ $s->drop_time ? substr($s->drop_time, 0, 5) : '' }}" class="form-control form-control-sm" aria-label="{{ __('Drop') }}"></td>
                        <td><input form="stop{{ $s->id }}" type="number" step="0.01" min="0" name="monthly_fee" value="{{ $s->monthly_fee }}" placeholder="{{ number_format((float) $route->monthly_fee, 0) }}" class="form-control form-control-sm" aria-label="{{ __('Fee') }}"></td>
                        <td class="text-end text-nowrap">
                            @can('transport.update')<button form="stop{{ $s->id }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Save') }}"><i class="bi bi-check-lg"></i></button>@endcan
                            @can('transport.delete')<form method="POST" action="{{ route('branch.transport.stops.destroy', $s) }}" class="d-inline" data-confirm="{{ __('Delete this stop?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>@endcan
                        </td>
                    </tr>
                @endforeach</tbody></table></div>
            @can('transport.update')
            <form method="POST" action="{{ route('branch.transport.stops.store', $route) }}" class="card-body border-top row g-2">@csrf
                <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="{{ __('New stop name') }}" required maxlength="120" aria-label="{{ __('Stop') }}"></div>
                <div class="col-md-2"><input type="time" name="pickup_time" class="form-control form-control-sm" aria-label="{{ __('Pickup') }}"></div>
                <div class="col-md-2"><input type="time" name="drop_time" class="form-control form-control-sm" aria-label="{{ __('Drop') }}"></div>
                <div class="col-md-2"><input type="number" step="0.01" min="0" name="monthly_fee" class="form-control form-control-sm" placeholder="{{ __('Fee') }}" aria-label="{{ __('Fee') }}"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-brand w-100">{{ __('Add stop') }}</button></div>
            </form>
            @endcan
        </div>
        <div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Students on this route') }} ({{ $riders->count() }})</div>
            @if($riders->isEmpty())<div class="card-body small text-body-secondary">{{ __('No students yet.') }}</div>
            @else<ul class="list-group list-group-flush small">@foreach($riders->sortBy(fn ($a) => $a->stop?->name) as $a)<li class="list-group-item d-flex justify-content-between"><span>{{ $a->student?->name }} <span class="text-body-secondary">{{ $a->student?->student_code }}</span></span><span class="text-body-secondary">{{ $a->stop?->name }} · ৳{{ number_format((float) $a->monthly_fee, 0) }}</span></li>@endforeach</ul>@endif
        </div>
    </div>
</div>
@endsection
