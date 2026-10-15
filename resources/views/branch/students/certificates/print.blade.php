{{-- A4 print view of a certificate. PDF export (mPDF, Bangla fonts, signatures) is added with the document templates in Part 6. --}}
@extends('layouts.base')
@section('title', $certificate->title().' '.$certificate->serial_no)

@php
    $d = $certificate->data;
    $logo = \App\Support\AuthTheme::logo($branch);
    $he = ($certificate->student?->gender ?? 'male') === 'female' ? ['she', 'her', 'daughter'] : ['he', 'his', 'son'];
    $parent = $d['father'] ?? $d['guardian'] ?? '—';
@endphp

@push('head')
<style>
    @page { size: A4; margin: 16mm; }
    body { background: #f1f5f9; }
    .cert { max-width: 780px; margin: 24px auto; background: #fff; border: 10px double #1e3a8a; padding: 36px 44px; font-family: 'Roboto','Hind Siliguri',serif; color: #111827; position: relative; }
    .cert h1 { font-size: 28px; letter-spacing: 1px; color: #1e3a8a; }
    .cert .meta { font-size: 13px; color: #475569; }
    .cert .body { font-size: 17px; line-height: 2; text-align: justify; }
    .cert .sig { border-top: 1px solid #111827; padding-top: 4px; width: 200px; text-align: center; font-size: 14px; }
    .cert .void { position: absolute; inset: 0; display: grid; place-items: center; font-size: 90px; color: rgba(220, 38, 38, .18); transform: rotate(-20deg); font-weight: 800; pointer-events: none; }
    @media print { body { background: #fff; } .no-print { display: none !important; } .cert { margin: 0; box-shadow: none; } }
</style>
@endpush

@section('body')
<div class="no-print text-center mt-3">
    <button type="button" class="btn btn-brand btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
</div>
<div class="cert shadow-sm">
    @if($certificate->isRevoked())<div class="void">{{ __('REVOKED') }}</div>@endif
    <div class="text-center mb-3">
        @if($logo)<img src="{{ $logo }}" alt="" style="height:70px" class="mb-2">@endif
        <div class="fs-4 fw-bold">{{ $branch->institution?->name }}</div>
        <div class="fw-semibold">{{ $branch->name }}</div>
        <div class="meta">{{ $branch->address }} @if($branch->phone) · {{ $branch->phone }}@endif</div>
    </div>
    <div class="d-flex justify-content-between meta mb-3">
        <span>{{ __('Serial no.') }}: <strong>{{ $certificate->serial_no }}</strong></span>
        <span>{{ __('Date') }}: <strong>{{ $certificate->issued_on->format('d M Y') }}</strong></span>
    </div>
    <h1 class="text-center text-uppercase mb-4">{{ $certificate->title() }}</h1>

    <div class="body">
        @include('branch.students.certificates._text', ['certificate' => $certificate])
    </div>

    <div class="d-flex justify-content-between align-items-end mt-5 pt-4">
        <div class="meta">{{ __('Verify at') }}:<br><strong>{{ $verifyUrl }}</strong><br>{{ __('Code') }}: <strong class="fs-6">{{ $certificate->verification_code }}</strong></div>
        <div class="sig">{{ $branch->principal_name ?: __('Principal') }}<br><small>{{ __('Principal / Head Teacher') }}</small></div>
    </div>
</div>
@endsection
