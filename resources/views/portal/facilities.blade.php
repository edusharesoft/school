@extends('layouts.portal')
@section('title', __('Transport & hostel'))

@section('content')
<x-page-header :title="__('Transport & hostel')" :breadcrumbs="$mode === 'parent' ? [__('My children') => route('portal.parent'), $student->name => null] : [__('Transport & hostel') => null]" />

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-bus-front me-1"></i>{{ __('School transport') }}</div>
            <div class="card-body">
                @if($transport)
                    <div class="h5 mb-1">{{ $transport['route']['name'] }} <span class="badge text-bg-light border">{{ $transport['route']['code'] }}</span></div>
                    <dl class="row small mb-0">
                        <dt class="col-5">{{ __('Your stop') }}</dt><dd class="col-7">{{ $transport['stop']['name'] }}</dd>
                        @if($transport['stop']['pickup_time'])<dt class="col-5">{{ __('Pick-up') }}</dt><dd class="col-7">{{ bn_digits($transport['stop']['pickup_time']) }}</dd>@endif
                        @if($transport['stop']['drop_time'])<dt class="col-5">{{ __('Drop-off') }}</dt><dd class="col-7">{{ bn_digits($transport['stop']['drop_time']) }}</dd>@endif
                        @if($transport['vehicle'])<dt class="col-5">{{ __('Vehicle') }}</dt><dd class="col-7">{{ $transport['vehicle']['registration_no'] }}@if($transport['vehicle']['name']) — {{ $transport['vehicle']['name'] }}@endif</dd>@endif
                        @if($transport['driver'])<dt class="col-5">{{ __('Driver') }}</dt><dd class="col-7">{{ $transport['driver']['name'] }} <a href="tel:{{ $transport['driver']['phone'] }}" class="ms-1">{{ $transport['driver']['phone'] }}</a></dd>@endif
                        @if($transport['helper'])<dt class="col-5">{{ __('Helper') }}</dt><dd class="col-7">{{ $transport['helper']['name'] }} <a href="tel:{{ $transport['helper']['phone'] }}" class="ms-1">{{ $transport['helper']['phone'] }}</a></dd>@endif
                        <dt class="col-5">{{ __('Monthly fee') }}</dt><dd class="col-7">৳{{ bn_digits(number_format($transport['monthly_fee'], 2)) }}</dd>
                    </dl>
                    @if($transport['tracking_url'])<a href="{{ $transport['tracking_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-3"><i class="bi bi-geo-alt me-1"></i>{{ __('Track the bus') }}</a>@endif
                @else
                    <p class="text-body-secondary mb-0">{{ __('No transport seat is assigned.') }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-building me-1"></i>{{ __('Hostel') }}</div>
            <div class="card-body">
                @if($hostel)
                    <div class="h5 mb-1">{{ $hostel['hostel']['name'] }}</div>
                    <dl class="row small mb-0">
                        <dt class="col-5">{{ __('Room') }}</dt><dd class="col-7">{{ $hostel['room']['room_no'] }}@if($hostel['bed']) — {{ __('bed') }} {{ $hostel['bed'] }}@endif @if($hostel['room']['building'] || $hostel['room']['floor'])<span class="text-body-secondary">({{ collect([$hostel['room']['building'], $hostel['room']['floor']])->filter()->implode(', ') }})</span>@endif</dd>
                        @if($hostel['warden'])<dt class="col-5">{{ __('Warden') }}</dt><dd class="col-7">{{ $hostel['warden']['name'] }} <a href="tel:{{ $hostel['warden']['phone'] }}" class="ms-1">{{ $hostel['warden']['phone'] }}</a></dd>@endif
                        <dt class="col-5">{{ __('Meal plan') }}</dt><dd class="col-7">{{ $hostel['meal_plan'] ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Monthly fee') }}</dt><dd class="col-7">৳{{ bn_digits(number_format($hostel['monthly_fee'], 2)) }}</dd>
                    </dl>
                    @if($hostel['recent_attendance'])
                        <div class="small fw-semibold mt-3 mb-1">{{ __('Recent roll call') }}</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($hostel['recent_attendance'] as $r)
                                <span class="badge {{ $r['status'] === 'present' ? 'text-bg-success' : ($r['status'] === 'absent' ? 'text-bg-danger' : 'text-bg-warning') }}" title="{{ __($r['status']) }}">{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d M') }}</span>
                            @endforeach
                        </div>
                    @endif
                @else
                    <p class="text-body-secondary mb-0">{{ __('This student does not live in the hostel.') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
<p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>{{ __('Transport and hostel fees appear with the other fees.') }}</p>
@endsection
