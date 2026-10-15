{{-- Mark sheets / academic transcripts, one A4 page per result. $results, $exam, $highest, branding. --}}
@extends('pdf._doc')
@section('title', __('Mark sheet').' — '.$exam->name)

@section('css')
    .grid td, .grid th { border: 0.2mm solid #94a3b8; padding: 1mm 1.4mm; font-size: 9.5pt; }
    .grid th { background-color: #eef2ff; }
    .sig { border-top: 0.2mm solid #111827; padding-top: 0.8mm; font-size: 9pt; text-align: center; }
@endsection

@php($labels = ['theory' => __('Theory'), 'practical' => __('Practical'), 'ca' => __('CA')])

@section('body')
@foreach($results as $r)
    @php($cols = collect($r->subjects)->flatMap(fn ($s) => array_keys($s['components'] ?? []))->unique()->sortBy(fn ($c) => array_search($c, ['theory', 'practical', 'ca']))->values())
    @if(! $loop->first)<pagebreak />@endif
    @include('pdf._letterhead')
    <table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="center bold" style="font-size:12pt; padding:3mm 0; color:{{ $accent }}">{{ mb_strtoupper(__('Academic transcript')) }} — {{ $exam->name }}@if($exam->status !== 'published') <span style="color:#b91c1c">({{ __('NOT PUBLISHED') }})</span>@endif</td></tr></table>

    <table style="width:100%; font-size:10pt; margin-bottom:3mm" cellpadding="0" cellspacing="0">
        <tr><td style="width:24mm; padding:0.8mm 0">{{ __('Name') }}</td><td style="width:62mm"><b>{{ $r->student?->name }}</b></td><td style="width:26mm">{{ __('Student ID') }}</td><td>{{ $r->student?->student_code }}</td></tr>
        <tr><td style="padding:0.8mm 0">{{ __('Class') }}</td><td>{{ $r->schoolClass?->name }}@if($r->studyGroup) ({{ $r->studyGroup->name }})@endif</td><td>{{ __('Section / Roll') }}</td><td>{{ $r->section?->name }} / {{ $r->roll_no }}</td></tr>
        <tr><td style="padding:0.8mm 0">{{ __('Session') }}</td><td>{{ $exam->session?->name }}</td><td>{{ __('Date of birth') }}</td><td>{{ $r->student?->date_of_birth?->format('d M Y') }}</td></tr>
    </table>

    <table class="grid" style="width:100%" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="text-align:left">{{ __('Subject') }}</th><th class="center">{{ __('Full') }}</th>
                @foreach($cols as $c)<th class="center">{{ $labels[$c] ?? $c }}</th>@endforeach
                <th class="center">{{ __('Total') }}</th><th class="center">{{ __('Highest') }}</th><th class="center">{{ __('Grade') }}</th><th class="center">{{ __('Point') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($r->subjects as $s)
            <tr>
                <td>{{ $s['subject'] }}@if(! empty($s['is_optional'])) <span style="font-size:7.5pt">({{ __('4th') }})</span>@endif</td>
                <td class="center">{{ (float) $s['full'] }}</td>
                @foreach($cols as $c)
                    <td class="center">@if(! empty($s['absent'])){{ __('Abs') }}@elseif(isset($s['components'][$c])){{ isset($s['marks'][$c]) && $s['marks'][$c] !== null ? (float) $s['marks'][$c] : '—' }}@endif</td>
                @endforeach
                <td class="center bold">{{ $s['total'] !== null ? (float) $s['total'] : '—' }}</td>
                <td class="center">{{ isset($highest[$s['paper_id']]) ? (float) $highest[$s['paper_id']] : '—' }}</td>
                <td class="center bold" @if(in_array($s['status'], ['fail', 'absent'], true)) style="color:#b91c1c" @endif>{{ $s['grade'] ?? '—' }}</td>
                <td class="center">{{ $s['point'] !== null ? number_format((float) $s['point'], 2) : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="grid" style="width:100%; margin-top:3mm" cellpadding="0" cellspacing="0">
        <tr>
            <th style="width:30mm; text-align:left">{{ __('Total marks') }}</th><td>{{ (float) $r->total_marks }} / {{ (float) $r->full_marks }} ({{ $r->percentage !== null ? $r->percentage.'%' : '—' }})</td>
            <th style="width:22mm; text-align:left">{{ __('GPA') }}</th><td><b>{{ $r->gpaLabel() }}</b>@if($r->gpa_without_optional !== null && $r->gpa_without_optional !== $r->gpa) <span style="font-size:8pt">({{ __('without 4th') }}: {{ number_format($r->gpa_without_optional, 2) }})</span>@endif</td>
        </tr>
        <tr>
            <th style="text-align:left">{{ __('Grade') }}</th><td><b>{{ $r->grade ?? '—' }}</b></td>
            <th style="text-align:left">{{ __('Result') }}</th><td><b>{{ __(ucfirst($r->status)) }}</b>@if($r->remarks) — {{ $r->remarks }}@endif</td>
        </tr>
        <tr>
            <th style="text-align:left">{{ __('Merit position') }}</th><td>{{ $r->class_position ? __(':class in class, :section in section', ['class' => $r->class_position, 'section' => $r->section_position]) : '—' }}</td>
            <th style="text-align:left">{{ __('Attendance') }}</th><td>{{ $r->attendance_pct !== null ? $r->attendance_pct.'%' : '—' }}</td>
        </tr>
    </table>

    @if($exam->gradingScale)
        <div style="font-size:8pt; color:#475569; margin-top:2mm">
            {{ __('Grading') }}: @foreach($exam->gradingScale->grades as $g){{ $g['grade'] }} {{ (float) $g['min'] }}–{{ (float) $g['max'] }}% ({{ number_format((float) $g['point'], 2) }})@if(! $loop->last) · @endif @endforeach
        </div>
    @endif

    <table style="width:100%; margin-top:18mm" cellpadding="0" cellspacing="0"><tr>
        <td style="width:30%" class="center"><div class="sig">{{ __('Class teacher') }}</div></td><td style="width:5%"></td>
        <td style="width:30%" class="center"><div class="sig">{{ __('Guardian') }}</div></td><td style="width:5%"></td>
        <td style="width:30%" class="center">@if($signature)<img src="{{ $signature }}" style="height:11mm" alt=""><br>@endif<div class="sig">{{ $signatory }}<br><span style="font-size:8pt">{{ __($signatory_title) }}</span></div></td>
    </tr></table>
    <div style="font-size:8pt; color:#64748b; margin-top:3mm">{{ __('Generated on :date', ['date' => now()->format('d M Y H:i')]) }}</div>
@endforeach
@endsection
