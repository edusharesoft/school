@extends('layouts.branch')
@section('title', __('Room routine'))

@section('content')
<x-page-header :title="__('Room routine')" :breadcrumbs="[__('Academics') => null, __('Class routine') => route('branch.academics.timetable.index'), __('Room routine') => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
    </x-slot:actions>
</x-page-header>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="academic_session_id" value="{{ $session?->id }}">
            <div class="col-md-5">
                <label class="form-label small" for="f_room">{{ __('Room') }}</label>
                <select id="f_room" name="room_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($rooms as $id => $name)<option value="{{ $id }}" @selected((int) $roomId === (int) $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-transparent">
        <span class="fw-semibold">{{ $rooms[$roomId] ?? '—' }}</span>
        <span class="small text-body-secondary ms-2">{{ $session?->name }}</span>
    </div>
    @if(! $session || ! $roomId)
        <x-empty-state icon="door-open" :title="__('No routine to show')" />
    @else
        @include('branch.academics.timetable._week', ['mode' => 'room'])
    @endif
</div>
@endsection
