@extends('layouts.branch')
@section('title', $vehicle->registration_no)

@section('content')
<x-page-header :title="$vehicle->label()" :breadcrumbs="[__('Vehicles') => route('branch.transport.vehicles.index'), $vehicle->registration_no => null]">
    @can('transport.update')
        <x-slot:actions><a href="{{ route('branch.transport.vehicles.edit', $vehicle) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a></x-slot:actions>
    @endcan
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-6">{{ __('Type') }}</dt><dd class="col-6">{{ __(ucfirst($vehicle->type)) }}</dd>
                <dt class="col-6">{{ __('Seats') }}</dt><dd class="col-6">{{ $vehicle->capacity }}</dd>
                <dt class="col-6">{{ __('Fuel') }}</dt><dd class="col-6">{{ __(ucfirst($vehicle->fuel_type)) }}</dd>
                <dt class="col-6">{{ __('Status') }}</dt><dd class="col-6">{{ __(ucfirst($vehicle->status)) }}</dd>
                @foreach(\App\Models\Transport\Vehicle::DOCUMENTS as $f => $label)
                    <dt class="col-6">{{ __($label) }}</dt><dd class="col-6 @if($vehicle->{$f} && $vehicle->{$f}->isPast()) text-danger fw-semibold @endif">{{ $vehicle->{$f}?->format('d M Y') ?? '—' }}</dd>
                @endforeach
                <dt class="col-6">{{ __('Routes') }}</dt><dd class="col-6">{{ $vehicle->routes->pluck('code')->implode(', ') ?: '—' }}</dd>
            </dl>
            @if($vehicle->tracking_url)<a href="{{ $vehicle->tracking_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-geo-alt me-1"></i>{{ __('Live tracking') }}</a>@endif
        </div></div>
        <div class="card"><div class="card-body small">
            <div class="d-flex justify-content-between"><span>{{ __('Maintenance cost') }}</span><strong>৳{{ number_format($totals['maintenance'], 2) }}</strong></div>
            <div class="d-flex justify-content-between"><span>{{ __('Fuel cost') }}</span><strong>৳{{ number_format($totals['fuel'], 2) }}</strong></div>
            <div class="d-flex justify-content-between"><span>{{ __('Fuel') }}</span><strong>{{ number_format($totals['litres'], 1) }} L</strong></div>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold">{{ __('Maintenance') }}</div>
            @can('transport.update')
            <form method="POST" action="{{ route('branch.transport.vehicles.maintenance.store', $vehicle) }}" class="card-body border-bottom row g-2">@csrf
                <div class="col-md-2"><input type="date" name="date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required aria-label="{{ __('Date') }}"></div>
                <div class="col-md-2"><select name="kind" class="form-select form-select-sm" aria-label="{{ __('Kind') }}">@foreach(\App\Models\Transport\Maintenance::KINDS as $k)<option value="{{ $k }}">{{ __(ucfirst($k)) }}</option>@endforeach</select></div>
                <div class="col-md-4"><input name="description" class="form-control form-control-sm" placeholder="{{ __('What was done') }}" required maxlength="255" aria-label="{{ __('Description') }}"></div>
                <div class="col-md-2"><input type="number" step="0.01" min="0" name="cost" class="form-control form-control-sm" placeholder="{{ __('Cost') }}" required aria-label="{{ __('Cost') }}"></div>
                <div class="col-md-2"><input type="date" name="next_due_on" class="form-control form-control-sm" aria-label="{{ __('Next due') }}" title="{{ __('Next due') }}"></div>
                <div class="col-12"><button class="btn btn-sm btn-brand">{{ __('Add') }}</button></div>
            </form>
            @endcan
            @if($maintenance->isEmpty())<div class="card-body small text-body-secondary">{{ __('No maintenance recorded.') }}</div>
            @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small"><tbody>@foreach($maintenance as $m)
                <tr><td>{{ $m->date->format('d M Y') }}</td><td>{{ __(ucfirst($m->kind)) }}</td><td>{{ $m->description }}@if($m->next_due_on)<div class="text-body-secondary">{{ __('Next due') }} {{ $m->next_due_on->format('d M Y') }}</div>@endif</td><td class="text-end">৳{{ number_format((float) $m->cost, 2) }}</td>
                    <td class="text-end">@can('transport.delete')<form method="POST" action="{{ route('branch.transport.maintenance.destroy', $m) }}" class="d-inline" data-confirm="{{ __('Delete this record?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>@endcan</td></tr>
            @endforeach</tbody></table></div>@endif
        </div>
        <div class="card">
            <div class="card-header bg-transparent fw-semibold">{{ __('Fuel log') }}</div>
            @can('transport.update')
            <form method="POST" action="{{ route('branch.transport.vehicles.fuel.store', $vehicle) }}" class="card-body border-bottom row g-2">@csrf
                <div class="col-md-2"><input type="date" name="date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required aria-label="{{ __('Date') }}"></div>
                <div class="col-md-2"><input type="number" step="0.01" min="0.01" name="litres" class="form-control form-control-sm" placeholder="{{ __('Litres') }}" required aria-label="{{ __('Litres') }}"></div>
                <div class="col-md-2"><input type="number" step="0.01" min="0" name="total_cost" class="form-control form-control-sm" placeholder="{{ __('Cost') }}" required aria-label="{{ __('Cost') }}"></div>
                <div class="col-md-2"><input type="number" min="0" name="odometer" class="form-control form-control-sm" placeholder="{{ __('Odometer') }}" aria-label="{{ __('Odometer') }}"></div>
                <div class="col-md-3"><select name="transport_driver_id" class="form-select form-select-sm" aria-label="{{ __('Driver') }}"><option value="">{{ __('Driver…') }}</option>@foreach($drivers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                <div class="col-md-1"><button class="btn btn-sm btn-brand">{{ __('Add') }}</button></div>
            </form>
            @endcan
            @if($fuel->isEmpty())<div class="card-body small text-body-secondary">{{ __('No fuel recorded.') }}</div>
            @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small"><tbody>@foreach($fuel as $f)
                <tr><td>{{ $f->date->format('d M Y') }}</td><td>{{ number_format((float) $f->litres, 1) }} L</td><td>{{ $f->odometer ? number_format($f->odometer).' km' : '—' }}</td><td>{{ $f->driver?->name ?? '—' }}</td><td class="text-end">৳{{ number_format((float) $f->total_cost, 2) }}</td>
                    <td class="text-end">@can('transport.delete')<form method="POST" action="{{ route('branch.transport.fuel.destroy', $f) }}" class="d-inline" data-confirm="{{ __('Delete this record?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>@endcan</td></tr>
            @endforeach</tbody></table></div>@endif
        </div>
    </div>
</div>
@endsection
