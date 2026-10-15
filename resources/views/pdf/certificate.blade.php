{{-- Certificate on A4 landscape. $certificate, $branch, $accent, $style (classic|modern|plain), $logo, $signature, $seal, $signatory, $signatory_title, $footer, $verifyUrl --}}
@extends('pdf._doc')
@section('title', $certificate->title().' '.$certificate->serial_no)

@section('css')
    .mt-2 { margin-top: 2mm; }
    .cert-title { font-size: 26pt; font-weight: bold; letter-spacing: 1pt; color: {{ $accent }}; }
    .cert-body { font-size: 14pt; line-height: 1.75; text-align: justify; }
    .small { font-size: 8.5pt; color: #475569; }
@endsection

@php
    $outer = match ($style) {
        'modern' => 'border-left:5mm solid '.$accent.'; border-top:0.3mm solid '.$accent.'; border-right:0.3mm solid '.$accent.'; border-bottom:0.3mm solid '.$accent,
        'plain' => 'border:0.3mm solid #94a3b8',
        default => 'border:2.4mm double '.$accent,
    };
@endphp

@section('body')
<table style="width:100%; height:186mm; border-collapse:separate; {{ $outer }}" cellpadding="0" cellspacing="0">
    <tr><td style="padding:7mm 12mm 4mm 12mm; height:146mm">
        <table style="width:100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:30mm">@if($logo)<img src="{{ $logo }}" style="height:22mm" alt="">@endif</td>
                <td class="center">
                    <div style="font-size:18pt; font-weight:bold; color:{{ $accent }}">{{ $branch->institution?->name }}</div>
                    <div style="font-size:12pt; font-weight:bold">{{ $branch->displayName() }}</div>
                    <div class="small">{{ $branch->address }}@if($branch->phone) · {{ $branch->phone }}@endif</div>
                </td>
                <td style="width:30mm" class="right small">
                    {{ __('Serial no.') }}<br><b>{{ $certificate->serial_no }}</b><br>
                    {{ __('Date') }}: <b>{{ $certificate->issued_on->format('d M Y') }}</b>
                </td>
            </tr>
        </table>
        {{-- mPDF ignores text-align on a block inside a table cell, so every centred line sits in a cell of its own --}}
        <table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="center cert-title" style="padding:7mm 0 6mm 0">{{ mb_strtoupper($certificate->title()) }}</td></tr></table>
        <div class="cert-body">
            @include($text ?? 'branch.students.certificates._text', ['certificate' => $certificate])
        </div>
    </td></tr>
    <tr><td style="padding:0 12mm 6mm 12mm; vertical-align:bottom">
        <table style="width:100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:78mm; vertical-align:bottom">
                    <table cellpadding="0" cellspacing="0"><tr>
                        @if(app(\App\Services\Documents\PdfService::class)->qrAvailable())<td style="width:21mm"><barcode code="{{ $verifyUrl }}" type="QR" error="M" size="0.8" disableborder="1" /></td>@endif
                        <td class="small" style="padding-left:2mm; vertical-align:middle">{{ __('Verify at') }}:<br>{{ preg_replace('#^https?://#', '', $verifyUrl) }}<br>{{ __('Code') }}: <b style="font-size:10pt">{{ $certificate->verification_code }}</b></td>
                    </tr></table>
                </td>
                <td class="center" style="vertical-align:bottom">@if($seal)<img src="{{ $seal }}" style="height:26mm" alt="">@endif</td>
                <td style="width:70mm; vertical-align:bottom" class="center">
                    @if($signature)<img src="{{ $signature }}" style="height:16mm" alt=""><br>@else<div style="height:16mm"></div>@endif
                    <div style="border-top:0.3mm solid #111827; padding-top:1mm; font-size:11pt; font-weight:bold">{{ $signatory }}</div>
                    <div class="small">{{ __($signatory_title) }}</div>
                </td>
            </tr>
        </table>
        @if($footer)<table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="center small" style="padding-top:2mm">{{ $footer }}</td></tr></table>@endif
    </td></tr>
</table>
@endsection
