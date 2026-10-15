@extends('layouts.base')
@section('title', __('Tabulation sheet').' — '.$exam->name)

@include('branch.exams.documents._print-styles', ['width' => '1400px', 'page' => 'A4 landscape'])

@section('body')
@include('branch.exams.documents._toolbar', ['back' => route('branch.exams.results.index', [$exam, 'class_id' => $results->first()->school_class_id])])
<div class="sheet">
    @if($exam->status !== 'published')<div class="stamp">{{ __('NOT PUBLISHED') }}</div>@endif
    @include('branch.exams.documents._letterhead', ['branch' => tenant()->branch()])
    <div class="doc-title">{{ __('Tabulation sheet') }} — {{ $exam->name }} ({{ $exam->session?->name }})</div>
    <div class="mb-2" style="font-size:13px">{{ __('Class') }}: <strong>{{ $results->first()->schoolClass?->name }}</strong>@if($section) · {{ __('Section') }}: <strong>{{ $section->name }}</strong>@endif</div>
    <div style="overflow-x:auto">
    <table style="font-size:11px">
        <thead>
            <tr>
                <th rowspan="2">{{ __('Roll') }}</th><th rowspan="2">{{ __('Student') }}</th>@if(! $section)<th rowspan="2">{{ __('Sec.') }}</th>@endif
                @foreach($columns as $col)<th class="text-center" colspan="2">{{ $col['code'] ?: $col['subject'] }}<div style="font-weight:400">{{ (float) $col['full'] }}</div></th>@endforeach
                <th rowspan="2" class="text-center">{{ __('Total') }}</th><th rowspan="2" class="text-center">{{ __('GPA') }}</th><th rowspan="2" class="text-center">{{ __('Grade') }}</th>
                <th rowspan="2" class="text-center">{{ __('Merit') }}</th><th rowspan="2">{{ __('Result') }}</th>
            </tr>
            <tr>@foreach($columns as $col)<th class="text-center" style="font-weight:400">{{ __('Mk') }}</th><th class="text-center" style="font-weight:400">{{ __('Gr') }}</th>@endforeach</tr>
        </thead>
        <tbody>
        @foreach($results as $r)
            @php($bySubject = collect($r->subjects)->keyBy('paper_id'))
            <tr>
                <td>{{ $r->roll_no }}</td>
                <td style="white-space:nowrap">{{ $r->student?->name }}<div style="color:#64748b">{{ $r->student?->student_code }}</div></td>
                @if(! $section)<td>{{ $r->section?->name }}</td>@endif
                @foreach($columns as $col)
                    @php($s = $bySubject->get($col['paper_id']))
                    <td class="text-center">{{ $s ? (! empty($s['absent']) ? __('Abs') : ($s['total'] !== null ? (float) $s['total'] : '—')) : '' }}</td>
                    <td class="text-center" @if($s && in_array($s['status'], ['fail', 'absent'], true)) style="color:#b91c1c;font-weight:700" @endif>{{ $s['grade'] ?? '' }}</td>
                @endforeach
                <td class="text-center fw-bold">{{ (float) $r->total_marks }}</td>
                <td class="text-center fw-bold">{{ $r->gpaLabel() }}</td>
                <td class="text-center">{{ $r->grade ?? '—' }}</td>
                <td class="text-center">{{ $section ? ($r->section_position ?? '—') : ($r->class_position ?? '—') }}</td>
                <td>{{ __(ucfirst($r->status)) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @php($passed = $results->where('status', 'pass')->count())
    @php($ranked = $results->whereIn('status', ['pass', 'fail'])->count())
    <div class="mt-2" style="font-size:12px">
        {{ __('Students') }}: {{ $results->count() }} · {{ __('Passed') }}: {{ $passed }} · {{ __('Failed') }}: {{ $results->where('status', 'fail')->count() }}
        · {{ __('Pass rate') }}: {{ $ranked ? round($passed / $ranked * 100, 1).'%' : '—' }}
        @foreach($results->where('status', 'pass')->countBy('grade')->sortKeys() as $g => $n) · {{ $g }}: {{ $n }}@endforeach
    </div>
    <div class="d-flex justify-content-between align-items-end" style="margin-top:48px">
        <div class="sig">{{ __('Prepared by') }}</div><div class="sig">{{ __('Checked by') }}</div><div class="sig">{{ __('Principal / Head Teacher') }}</div>
    </div>
</div>
@endsection
