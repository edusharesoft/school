@extends('layouts.branch')
@section('title', $room->exists ? __('Edit room') : __('Add room'))

@section('content')
<x-page-header :title="$room->exists ? __('Edit room') : __('Add room')" :breadcrumbs="[__('Rooms') => route('branch.hostel.rooms.index'), ($room->exists ? __('Edit') : __('New')) => null]" />
<form method="POST" class="card" style="max-width: 800px" action="{{ $room->exists ? route('branch.hostel.rooms.update', $room) : route('branch.hostel.rooms.store') }}">
    @csrf @if($room->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.select name="hostel_id" :label="__('Hostel')" :options="$hostels" :value="$room->hostel_id" required col="col-md-6" />
        <x-form.input name="building" :label="__('Building / block')" :value="$room->building" maxlength="60" col="col-md-3" />
        <x-form.input name="floor" :label="__('Floor')" :value="$room->floor" maxlength="30" col="col-md-3" />
        <x-form.input name="room_no" :label="__('Room number')" :value="$room->room_no" required maxlength="30" col="col-md-4" />
        <x-form.select name="type" :label="__('Type')" :options="collect(\App\Models\Hostel\Room::TYPES)->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])->all()" :value="$room->type" required col="col-md-4" />
        <x-form.input name="monthly_fee" type="number" step="0.01" min="0" :label="__('Monthly fee per bed (৳)')" :value="$room->monthly_fee ?? 0" required col="col-md-4" :help="__('Residents keep the fee they were allocated at; a change applies to new allocations.')" />
        @unless($room->exists)<x-form.input name="beds" type="number" min="1" max="60" :label="__('Number of beds')" value="2" required col="col-md-4" />@endunless
        <x-form.switch name="is_active" :label="__('In use')" :checked="$room->exists ? $room->is_active : true" />
    </div>
    <x-form.actions :cancel="route('branch.hostel.rooms.index')" />
</form>
@endsection
