@extends('layouts.branch')
@section('title', __('Room').' '.$room->room_no)

@section('content')
<x-page-header :title="$room->hostel?->name.' — '.__('Room').' '.$room->room_no" :breadcrumbs="[__('Rooms') => route('branch.hostel.rooms.index'), $room->room_no => null]">
    @can('hostel.update')
        <x-slot:actions><a href="{{ route('branch.hostel.rooms.edit', $room) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a></x-slot:actions>
    @endcan
</x-page-header>
<div class="card" style="max-width: 760px">
    <div class="card-header bg-transparent d-flex justify-content-between"><span class="fw-semibold">{{ __('Beds') }}</span><span class="small text-body-secondary">{{ __(ucfirst($room->type)) }} · ৳{{ number_format((float) $room->monthly_fee, 0) }} {{ __('per bed') }}</span></div>
    <ul class="list-group list-group-flush small">@foreach($beds as $b)
        @php($res = $residents->get($b->id))
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><strong>{{ $b->label }}</strong> @if($res)— {{ $res->student?->name }} <span class="text-body-secondary">{{ $res->student?->student_code }}</span>@elseif($b->is_active)— <span class="text-success">{{ __('Free') }}</span>@else— <span class="text-body-secondary">{{ __('Out of use') }}</span>@endif</span>
            @can('hostel.update')@unless($res)<form method="POST" action="{{ route('branch.hostel.beds.toggle', $b) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $b->is_active ? __('Take out of use') : __('Put in use') }}</button></form>@endunless @endcan
        </li>
    @endforeach</ul>
    @can('hostel.update')
    <form method="POST" action="{{ route('branch.hostel.rooms.beds.store', $room) }}" class="card-footer bg-transparent d-flex gap-2 align-items-center">@csrf
        <input type="number" name="count" min="1" max="20" value="1" class="form-control form-control-sm" style="width:80px" aria-label="{{ __('How many') }}"><button class="btn btn-sm btn-brand">{{ __('Add beds') }}</button></form>
    @endcan
</div>
@endsection
