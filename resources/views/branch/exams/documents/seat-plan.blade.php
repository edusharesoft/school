@extends('layouts.base')
@section('title', __('Seat plan').' — '.$exam->name)

@include('branch.exams.documents._print-styles')

@section('body')
@php($branch = tenant()->branch())
<div class="no-print container my-3" style="max-width:800px">
    <form method="GET" class="card card-body">
        <div class="small fw-semibold mb-2">{{ __('Rooms to use (all when none is ticked)') }}</div>
        <div class="d-flex flex-wrap gap-3 mb-2">
            @forelse($allRooms as $room)
                <div class="form-check"><input class="form-check-input" type="checkbox" name="rooms[]" value="{{ $room->id }}" id="r{{ $room->id }}" @checked(in_array($room->id, $selected, true))>
                    <label class="form-check-label small" for="r{{ $room->id }}">{{ $room->name }} ({{ $room->capacity }})</label></div>
            @empty
                <span class="small text-danger">{{ __('Add rooms with a capacity under Academics → Rooms first.') }}</span>
            @endforelse
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary">{{ __('Arrange') }}</button>
            <button type="button" class="btn btn-brand btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
            <a href="{{ route('branch.exams.show', $exam) }}" class="btn btn-outline-secondary btn-sm ms-auto">{{ __('Back') }}</a>
        </div>
        @if($plan['unseated']->isNotEmpty())
            <div class="alert alert-warning small mt-2 mb-0">{{ __(':count candidate(s) could not be seated — select more rooms.', ['count' => $plan['unseated']->count()]) }}</div>
        @endif
    </form>
</div>

@foreach($plan['rooms'] as $block)
    <div class="sheet {{ $loop->last ? '' : 'page-break' }}">
        @include('branch.exams.documents._letterhead')
        <div class="doc-title">{{ __('Seat plan') }} — {{ $exam->name }}</div>
        <div class="mb-2" style="font-size:14px">{{ __('Room') }}: <strong>{{ $block['room']->name }}</strong> ({{ $block['room']->code }}) · {{ __('Candidates') }}: {{ $block['seats']->count() }} / {{ $block['room']->capacity }}</div>
        <table>
            <thead><tr><th style="width:60px">{{ __('Seat') }}</th><th>{{ __('Student ID') }}</th><th>{{ __('Name') }}</th><th>{{ __('Class') }}</th><th>{{ __('Section') }}</th><th>{{ __('Roll') }}</th><th style="width:120px">{{ __('Signature') }}</th></tr></thead>
            <tbody>
            @foreach($block['seats'] as $i => $e)
                <tr><td class="text-center">{{ $i + 1 }}</td><td>{{ $e->student?->student_code }}</td><td>{{ $e->student?->name }}</td><td>{{ $e->schoolClass?->name }}</td><td>{{ $e->section?->name }}</td><td>{{ $e->roll_no }}</td><td></td></tr>
            @endforeach
            </tbody>
        </table>
        <div class="d-flex justify-content-between" style="margin-top:40px"><div class="sig">{{ __('Invigilator') }}</div><div class="sig">{{ __('Controller of exams') }}</div></div>
    </div>
@endforeach
@if($plan['rooms']->isEmpty())
    <p class="text-center text-body-secondary">{{ __('No candidates or rooms to arrange.') }}</p>
@endif
@endsection
