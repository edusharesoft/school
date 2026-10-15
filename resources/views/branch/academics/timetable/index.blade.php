@extends('layouts.branch')
@section('title', __('Class routine'))

@php($canEdit = auth()->user()->can('timetables.update') && ! $print)

@push('head')
<style>
    .routine th, .routine td { vertical-align: top; min-width: 150px; }
    .routine th:first-child, .routine td:first-child { min-width: 96px; position: sticky; left: 0; background: var(--bs-body-bg); z-index: 1; }
    .routine .cell select, .routine .cell input { font-size: .78rem; padding-top: .15rem; padding-bottom: .15rem; }
    .routine .break { background: repeating-linear-gradient(45deg, rgba(0,0,0,.03), rgba(0,0,0,.03) 6px, transparent 6px, transparent 12px); text-align: center; vertical-align: middle; color: var(--bs-secondary-color); }
    .routine .has-conflict { outline: 2px solid var(--bs-danger); outline-offset: -2px; }
    @media print { .routine th, .routine td { min-width: 0; font-size: 11px; } .routine th:first-child, .routine td:first-child { position: static; } }
</style>
@endpush

@section('content')
<x-page-header :title="__('Class routine')" :breadcrumbs="[__('Academics') => null, __('Class routine') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.academics.periods.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock me-1"></i>{{ __('Periods') }}</a>
        <a href="{{ route('branch.academics.timetable.teacher', ['academic_session_id' => $session?->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-video3 me-1"></i>{{ __('Teacher routine') }}</a>
        <a href="{{ route('branch.academics.timetable.room', ['academic_session_id' => $session?->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-door-open me-1"></i>{{ __('Room routine') }}</a>
        @if($section)
            <a href="{{ route('branch.academics.timetable.index', ['academic_session_id' => $session?->id, 'section_id' => $section->id, 'print' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>{{ __('Print view') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="f_session">{{ __('Session') }}</label>
                <select id="f_session" name="academic_session_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($sessions as $s)<option value="{{ $s->id }}" @selected($session?->id === $s->id)>{{ $s->name }}{{ $s->is_current ? ' ★' : '' }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small" for="f_section">{{ __('Class & section') }}</label>
                <select id="f_section" name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($sections as $s)<option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->label() }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

@if(! $session || ! $section)
    <div class="card"><x-empty-state icon="calendar3-week" :title="__('Select a session and a section')" :message="__('Create an academic session and sections first.')" /></div>
@elseif($periods->isEmpty())
    <div class="card"><x-empty-state icon="clock" :title="__('No periods yet')" :message="__('Add the bell schedule before building routines.')">
        @can('timetables.create')<a href="{{ route('branch.academics.periods.index') }}" class="btn btn-brand btn-sm mt-2">{{ __('Add periods') }}</a>@endcan
    </x-empty-state></div>
@else
    <form method="POST" action="{{ route('branch.academics.timetable.store') }}" class="card">
        @csrf
        <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
        <input type="hidden" name="section_id" value="{{ $section->id }}">
        <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">{{ $section->label() }}</div>
                <div class="small text-body-secondary">{{ $session->name }} · {{ __(ucfirst($section->shift ?? 'day')) }} {{ __('shift') }}</div>
            </div>
            @if($print)
                <button type="button" class="btn btn-brand btn-sm no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
            @endif
        </div>

        @if($errors->any())
            <div class="alert alert-danger m-3 mb-0">
                <div class="fw-semibold mb-1">{{ __('The routine was not saved:') }}</div>
                <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered routine mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Day') }}</th>
                        @foreach($periods as $p)
                            <th class="text-center"><div>{{ $p->name }}</div><div class="small fw-normal text-body-secondary">{{ $p->timeRange() }}</div></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($days as $day)
                    <tr>
                        <th>{{ App\Models\Academic\TimetableEntry::dayName($day) }}</th>
                        @foreach($periods as $p)
                            @if($p->is_break)
                                @if($loop->parent->first)
                                    <td class="break" rowspan="{{ count($days) }}"><span class="small">{{ $p->name }}</span></td>
                                @endif
                                @continue
                            @endif
                            @php($e = $grid->get($day.'-'.$p->id))
                            @php($key = "cells.$day.{$p->id}")
                            @php($conflict = $errors->has("$key.teacher_id") || $errors->has("$key.room_id") || $errors->has("$key.subject_id"))
                            <td @class(['cell', 'has-conflict' => $conflict])>
                                @if($canEdit)
                                    @php($sub = old("$key.subject_id", $e?->subject_id))
                                    @php($tea = old("$key.teacher_id", $e?->teacher_id))
                                    @php($roo = old("$key.room_id", $e?->room_id))
                                    <select name="cells[{{ $day }}][{{ $p->id }}][subject_id]" class="form-select form-select-sm mb-1" aria-label="{{ __('Subject') }}">
                                        <option value="">— {{ __('Subject') }} —</option>
                                        @foreach($subjects as $id => $name)<option value="{{ $id }}" @selected((string) $sub === (string) $id)>{{ $name }}</option>@endforeach
                                    </select>
                                    <select name="cells[{{ $day }}][{{ $p->id }}][teacher_id]" @class(['form-select form-select-sm mb-1', 'is-invalid' => $errors->has("$key.teacher_id")]) aria-label="{{ __('Teacher') }}">
                                        <option value="">— {{ __('Teacher') }} —</option>
                                        @foreach($teachers as $id => $name)<option value="{{ $id }}" @selected((string) $tea === (string) $id)>{{ $name }}</option>@endforeach
                                    </select>
                                    <select name="cells[{{ $day }}][{{ $p->id }}][room_id]" @class(['form-select form-select-sm', 'is-invalid' => $errors->has("$key.room_id")]) aria-label="{{ __('Room') }}">
                                        <option value="">— {{ __('Room') }} —</option>
                                        @foreach($rooms as $id => $name)<option value="{{ $id }}" @selected((string) $roo === (string) $id)>{{ $name }}</option>@endforeach
                                    </select>
                                    @if($conflict)<div class="text-danger small mt-1">{{ $errors->first("$key.teacher_id") ?: $errors->first("$key.room_id") ?: $errors->first("$key.subject_id") }}</div>@endif
                                @elseif($e)
                                    <div class="fw-semibold">{{ $e->subject?->name ?? $e->note }}</div>
                                    <div class="small">{{ $e->teacher?->name }}</div>
                                    @if($e->room)<div class="small text-body-secondary"><i class="bi bi-door-open"></i> {{ $e->room->name }}</div>@endif
                                @else
                                    <span class="text-body-tertiary small">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if($canEdit)
            <div class="card-footer bg-transparent d-flex flex-wrap gap-2 align-items-center">
                <button class="btn btn-brand" type="submit"><i class="bi bi-check2 me-1"></i>{{ __('Save routine') }}</button>
                <span class="small text-body-secondary">{{ __('Leave a cell empty for a free period. Teacher and room double-booking across sections is checked when you save.') }}</span>
            </div>
        @endif
    </form>
@endif
@endsection
