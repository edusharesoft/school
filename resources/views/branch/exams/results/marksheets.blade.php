{{-- Mark sheet / report card: one A4 page per result. Used by the back-office, the portals and the public lookup. --}}
@extends('layouts.base')
@section('title', __('Mark sheet').' — '.$exam->name)

@include('branch.exams.documents._print-styles')

@php($labels = ['theory' => __('Theory'), 'practical' => __('Practical'), 'ca' => __('CA')])

@section('body')
@include('branch.exams.documents._toolbar', ['back' => $back ?? null])
@foreach($results as $r)
    @php($cols = collect($r->subjects)->flatMap(fn ($s) => array_keys($s['components'] ?? []))->unique()->sortBy(fn ($c) => array_search($c, ['theory', 'practical', 'ca']))->values())
    <div class="sheet {{ $loop->last ? '' : 'page-break' }}">
        @if($exam->status !== 'published')<div class="stamp">{{ __('NOT PUBLISHED') }}</div>@endif
        @include('branch.exams.documents._letterhead')
        <div class="doc-title">{{ __('Academic transcript') }} — {{ $exam->name }}</div>

        <table class="noborder mb-2" style="font-size:13px">
            <tr><td style="width:18%">{{ __('Name') }}</td><td style="width:40%"><strong>{{ $r->student?->name }}</strong></td><td style="width:18%">{{ __('Student ID') }}</td><td>{{ $r->student?->student_code }}</td></tr>
            <tr><td>{{ __('Class') }}</td><td>{{ $r->schoolClass?->name }}@if($r->studyGroup) ({{ $r->studyGroup->name }})@endif</td><td>{{ __('Section / Roll') }}</td><td>{{ $r->section?->name }} / {{ $r->roll_no }}</td></tr>
            <tr><td>{{ __('Session') }}</td><td>{{ $exam->session?->name }}</td><td>{{ __('Date of birth') }}</td><td>{{ $r->student?->date_of_birth?->format('d M Y') }}</td></tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>{{ __('Subject') }}</th><th class="text-center">{{ __('Full') }}</th>
                    @foreach($cols as $c)<th class="text-center">{{ $labels[$c] ?? $c }}</th>@endforeach
                    <th class="text-center">{{ __('Total') }}</th><th class="text-center">{{ __('Highest') }}</th>
                    <th class="text-center">{{ __('Grade') }}</th><th class="text-center">{{ __('Point') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($r->subjects as $s)
                <tr>
                    <td>{{ $s['subject'] }}@if(! empty($s['is_optional'])) <small>({{ __('4th') }})</small>@endif</td>
                    <td class="text-center">{{ (float) $s['full'] }}</td>
                    @foreach($cols as $c)
                        <td class="text-center">@if(! empty($s['absent'])){{ __('Abs') }}@elseif(isset($s['components'][$c])){{ isset($s['marks'][$c]) && $s['marks'][$c] !== null ? (float) $s['marks'][$c] : '—' }}@endif</td>
                    @endforeach
                    <td class="text-center fw-bold">{{ $s['total'] !== null ? (float) $s['total'] : '—' }}</td>
                    <td class="text-center">{{ isset($highest[$s['paper_id']]) ? (float) $highest[$s['paper_id']] : '—' }}</td>
                    <td class="text-center fw-bold" @if(in_array($s['status'], ['fail', 'absent'], true)) style="color:#b91c1c" @endif>{{ $s['grade'] ?? '—' }}</td>
                    <td class="text-center">{{ $s['point'] !== null ? number_format((float) $s['point'], 2) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="mt-3" style="width:100%">
            <tr>
                <th>{{ __('Total marks') }}</th><td>{{ (float) $r->total_marks }} / {{ (float) $r->full_marks }} ({{ $r->percentage !== null ? $r->percentage.'%' : '—' }})</td>
                <th>{{ __('GPA') }}</th><td><strong>{{ $r->gpaLabel() }}</strong>@if($r->gpa_without_optional !== null && $r->gpa_without_optional !== $r->gpa) <small>({{ __('without 4th') }}: {{ number_format($r->gpa_without_optional, 2) }})</small>@endif</td>
            </tr>
            <tr>
                <th>{{ __('Grade') }}</th><td><strong>{{ $r->grade ?? '—' }}</strong></td>
                <th>{{ __('Result') }}</th><td><strong>{{ __(ucfirst($r->status)) }}</strong>@if($r->remarks) — {{ $r->remarks }}@endif</td>
            </tr>
            <tr>
                <th>{{ __('Merit position') }}</th><td>{{ $r->class_position ? __(':class in class, :section in section', ['class' => $r->class_position, 'section' => $r->section_position]) : '—' }}</td>
                <th>{{ __('Attendance') }}</th><td>{{ $r->attendance_pct !== null ? $r->attendance_pct.'%' : '—' }}</td>
            </tr>
        </table>

        @if($exam->gradingScale)
            <div class="mt-2" style="font-size:11px;color:#475569">
                {{ __('Grading') }}: @foreach($exam->gradingScale->grades as $g){{ $g['grade'] }} {{ (float) $g['min'] }}–{{ (float) $g['max'] }}% ({{ number_format((float) $g['point'], 2) }})@if(! $loop->last) · @endif @endforeach
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-end" style="margin-top:56px">
            <div class="sig">{{ __('Class teacher') }}</div>
            <div class="sig">{{ __('Guardian') }}</div>
            <div class="sig">{{ $branch->principal_name ?: __('Principal') }}<br><small>{{ __('Principal / Head Teacher') }}</small></div>
        </div>
        <div style="font-size:11px;color:#64748b;margin-top:10px">{{ __('Generated on :date', ['date' => now()->format('d M Y H:i')]) }}</div>
    </div>
@endforeach
@endsection
