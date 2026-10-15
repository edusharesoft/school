@extends('layouts.base')
@section('title', __('Admit cards').' — '.$exam->name)

@include('branch.exams.documents._print-styles')
@include('branch.exams.documents._admit-styles')

@section('body')
@php($branch = tenant()->branch())
<div class="no-print container my-3" style="max-width:800px">
    <form method="GET" class="card card-body d-flex flex-row flex-wrap align-items-end gap-2">
        <div>
            <label class="form-label small mb-1" for="f_sec">{{ __('Class & section') }}</label>
            <select id="f_sec" name="section_id" class="form-select form-select-sm" required>
                <option value="">— {{ __('Select') }} —</option>
                @foreach($sections as $s)<option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->label() }}</option>@endforeach
            </select>
        </div>
        <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="include_ineligible" value="1" id="f_inel" @checked($includeIneligible)>
            <label class="form-check-label small" for="f_inel">{{ __('Also print ineligible students (marked)') }}</label></div>
        <button class="btn btn-sm btn-outline-secondary">{{ __('Show') }}</button>
        @if($section)<button type="button" class="btn btn-brand btn-sm ms-auto" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
            <a href="{{ route('branch.exams.admit-cards.pdf', array_filter(['exam' => $exam, 'section_id' => $section->id, 'include_ineligible' => $includeIneligible ? 1 : null])) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>{{ __('PDF') }}</a>@endif
        <a href="{{ route('branch.exams.show', $exam) }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
    </form>
    @if($section)
        @php($ineligible = $cards->where('eligible', false))
        @if($exam->min_attendance !== null)
            <div class="alert {{ $ineligible->isEmpty() ? 'alert-success' : 'alert-warning' }} small mt-2 mb-0">
                {{ __('Minimum attendance :min% — :count student(s) below it.', ['min' => $exam->min_attendance, 'count' => $ineligible->count()]) }}
                @foreach($ineligible as $c)<br>{{ $c['enrollment']->roll_no }}. {{ $c['enrollment']->student?->name }} — {{ $c['attendance'] }}%@endforeach
            </div>
        @endif
    @endif
</div>

@if($section)
<div class="sheet" style="padding-top:14px">
    @forelse($cards->filter(fn ($c) => $c['eligible'] || $includeIneligible) as $card)
        @include('branch.exams.documents._admit-card', ['photo' => $card['enrollment']->student?->photo_path ? route('branch.students.photo', $card['enrollment']->student) : null])
    @empty
        <p class="text-center text-body-secondary">{{ __('No students to print.') }}</p>
    @endforelse
</div>
@endif
@endsection
