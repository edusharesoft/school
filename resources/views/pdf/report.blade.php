{{-- Generic report PDF. $result (ReportResult), $accent, $logo (data URI|null), $institution, $branchName, $by --}}
@extends('pdf._doc')
@php
    $n = count($result->columns);
    $font = $n <= 6 ? '9.5pt' : ($n <= 9 ? '8.5pt' : ($n <= 12 ? '7.5pt' : '6.5pt'));
    $totals = $result->hasTotals() ? $result->totals() : null;
@endphp
@section('title', $result->title)
@section('css')
    .rpt th { background: #e2e8f0; border: 0.2mm solid #94a3b8; padding: 1.2mm 1.5mm; font-size: {{ $font }}; text-align: left; }
    .rpt td { border: 0.2mm solid #cbd5e1; padding: 1mm 1.5mm; font-size: {{ $font }}; }
    .rpt tr.total td { font-weight: bold; background: #f1f5f9; border-top: 0.4mm solid #475569; }
    .meta td { font-size: 8.5pt; color: #475569; padding: 0.4mm 0; }
@endsection
@section('body')
<table style="width:100%; border-bottom:0.5mm solid {{ $accent }}" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:18mm; padding-bottom:1.5mm">@if($logo)<img src="{{ $logo }}" style="height:13mm" alt="">@endif</td>
        <td style="padding-bottom:1.5mm">
            <div style="font-size:13pt; font-weight:bold; color:{{ $accent }}">{{ $institution }}</div>
            @if($branchName)<div style="font-size:10pt; font-weight:bold">{{ $branchName }}</div>@endif
        </td>
        <td class="right" style="padding-bottom:1.5mm; font-size:8.5pt; color:#475569">{{ __('Generated') }}: {{ now()->format('d/m/Y H:i') }}@if($by)<br>{{ $by }}@endif</td>
    </tr>
</table>

<div style="font-size:14pt; font-weight:bold; margin-top:3mm">{{ $result->title }}</div>
@if($result->subtitle)<div style="font-size:9.5pt; color:#475569">{{ $result->subtitle }}</div>@endif

@if($result->filters || $result->summary)
    <table class="meta" style="margin:2mm 0 3mm" cellpadding="0" cellspacing="0">
        @foreach($result->filters as $label => $value)
            <tr><td style="padding-right:4mm">{{ $label }}</td><td class="bold">{{ $value }}</td></tr>
        @endforeach
        @foreach($result->summary as $label => $value)
            <tr><td style="padding-right:4mm">{{ $label }}</td><td class="bold">{{ is_numeric($value) ? number_format((float) $value, floor((float) $value) == (float) $value ? 0 : 2) : $value }}</td></tr>
        @endforeach
    </table>
@endif

<table class="rpt" style="width:100%; margin-top:2mm" cellpadding="0" cellspacing="0" repeat_header="1">
    <thead>
        <tr>
            @foreach($result->columns as $c)
                <th @if($result->isNumeric($c)) style="text-align:right" @endif>{{ $c['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($result->rows as $row)
            <tr>
                @foreach($result->columns as $c)
                    <td @if($result->isNumeric($c)) style="text-align:right" @endif>{{ \App\Services\Reports\ReportResult::display($row[$c['key']] ?? null, $c['type']) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ $n }}" class="center muted" style="padding:4mm">{{ __('Nothing to show for these filters.') }}</td></tr>
        @endforelse
        @if($totals)
            <tr class="total">
                @foreach($result->columns as $i => $c)
                    <td @if($result->isNumeric($c)) style="text-align:right" @endif>
                        @if(array_key_exists($c['key'], $totals)){{ \App\Services\Reports\ReportResult::display($totals[$c['key']], $c['type']) }}@elseif($i === 0){{ __('Total') }}@endif
                    </td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>

@foreach($result->notes as $note)
    <div style="font-size:8pt; color:#64748b; margin-top:1.5mm">{{ $note }}</div>
@endforeach
@endsection
