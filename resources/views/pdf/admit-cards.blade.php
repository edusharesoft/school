{{-- Admit cards, two per A4 page. $cards = ExamDocumentService rows (+ 'photo'), $exam, $section, branding. --}}
@extends('pdf._doc')
@section('title', __('Admit card').' — '.$exam->name)

@section('css')
    .grid td, .grid th { border: 0.2mm solid #94a3b8; padding: 1mm 1.6mm; font-size: 9pt; }
    .grid th { background-color: #eef2ff; }
    .sig { border-top: 0.2mm solid #111827; padding-top: 0.8mm; font-size: 8.5pt; text-align: center; }
@endsection

@section('body')
@foreach($cards as $card)
    @php($e = $card['enrollment'])
    @if(! $loop->first && ($loop->index % 2) === 0)<pagebreak />@endif
    <table style="width:100%; height:132mm; border-collapse:separate; border:0.4mm solid {{ $accent }}; margin-bottom:{{ ($loop->index % 2) === 0 ? '6mm' : '0' }}" cellpadding="0" cellspacing="0">
        <tr><td style="padding:3mm 5mm; height:130mm">
            @include('pdf._letterhead')
            <table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="center bold" style="font-size:12pt; padding:2mm 0; color:{{ $accent }}">{{ mb_strtoupper(__('Admit card')) }} — {{ $exam->name }}@unless($card['eligible']) <span style="color:#b91c1c">({{ __('NOT ELIGIBLE') }})</span>@endunless</td></tr></table>
            <table style="width:100%" cellpadding="0" cellspacing="0"><tr>
                <td>
                    <table style="width:100%; font-size:10pt" cellpadding="0" cellspacing="0">
                        <tr><td style="width:34mm; padding:0.8mm 0">{{ __('Name') }}</td><td><b>{{ $e->student?->name }}</b></td></tr>
                        <tr><td style="padding:0.8mm 0">{{ __('Student ID') }}</td><td>{{ $e->student?->student_code }}</td></tr>
                        <tr><td style="padding:0.8mm 0">{{ __('Class / Section / Roll') }}</td><td>{{ $section->schoolClass?->name }} / {{ $section->name }} / <b>{{ $e->roll_no }}</b>@if($e->studyGroup) · {{ $e->studyGroup->name }}@endif</td></tr>
                        <tr><td style="padding:0.8mm 0">{{ __('Session') }}</td><td>{{ $exam->session?->name }}</td></tr>
                    </table>
                </td>
                <td style="width:26mm" class="right">
                    @if(! empty($card['photo']))<img src="{{ $card['photo'] }}" style="width:24mm; height:29mm; border:0.2mm solid #94a3b8" alt="">@else<table style="width:24mm; height:29mm; border-collapse:separate; border:0.2mm solid #94a3b8; margin-left:auto" cellpadding="0" cellspacing="0"><tr><td style="height:29mm; text-align:center; vertical-align:middle; font-size:8pt; color:#64748b">{{ __('Photo') }}</td></tr></table>@endif
                </td>
            </tr></table>
            <table class="grid" style="width:100%; margin-top:3mm" cellpadding="0" cellspacing="0">
                <thead><tr><th style="width:42mm">{{ __('Date') }}</th><th style="width:28mm">{{ __('Time') }}</th><th>{{ __('Subject') }}</th></tr></thead>
                <tbody>
                @foreach($card['papers'] as $p)
                    <tr><td>{{ $p->exam_date?->format('d M Y, D') ?? '—' }}</td><td>{{ $p->starts_at ? substr($p->starts_at, 0, 5) : '' }}@if($p->ends_at)–{{ substr($p->ends_at, 0, 5) }}@endif</td><td>{{ $p->subject?->name }}</td></tr>
                @endforeach
                </tbody>
            </table>
            @if($exam->instructions)<div style="font-size:8pt; color:#334155; margin-top:2mm">{!! nl2br(e($exam->instructions)) !!}</div>@endif
            <table style="width:100%; margin-top:10mm" cellpadding="0" cellspacing="0"><tr>
                <td style="width:30%" class="center"><div class="sig">{{ __('Class teacher') }}</div></td><td style="width:5%"></td>
                <td style="width:30%" class="center"><div class="sig">{{ __('Controller of exams') }}</div></td><td style="width:5%"></td>
                <td style="width:30%" class="center">@if($signature)<img src="{{ $signature }}" style="height:9mm" alt=""><br>@endif<div class="sig">{{ $signatory }}</div></td>
            </tr></table>
        </td></tr>
    </table>
@endforeach
@endsection
