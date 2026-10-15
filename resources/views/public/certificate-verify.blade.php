@extends('layouts.public')
@section('title', __('Verify certificate'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <form method="GET" class="dp blue mb-4">
            <div class="dp-head"><h2><i class="fa-solid fa-certificate me-1"></i> {{ __('Verify a certificate') }}</h2></div>
            <div class="dp-body d-flex gap-2">
                <input name="code" value="{{ $code }}" class="form-control text-uppercase" placeholder="{{ __('Verification code printed on the certificate') }}" required maxlength="20" aria-label="{{ __('Verification code') }}">
                <button class="btn btn-brand">{{ __('Verify') }}</button>
            </div>
        </form>
        @if($code !== '' && ! $certificate)
            <div class="alert alert-danger"><i class="fa-solid fa-circle-xmark me-1"></i> {{ __('No certificate with this code was issued by this branch.') }}</div>
        @elseif($certificate)
            <div @class(['alert', 'alert-success' => ! $certificate->isRevoked(), 'alert-danger' => $certificate->isRevoked()])>
                <div class="fw-bold mb-1">
                    @if($certificate->isRevoked())<i class="fa-solid fa-ban me-1"></i> {{ __('This certificate has been revoked.') }}
                    @else<i class="fa-solid fa-circle-check me-1"></i> {{ __('Genuine certificate') }}@endif
                </div>
                <div>{{ $certificate->title() }} · <code>{{ $certificate->serial_no }}</code></div>
                <div>{{ __('Issued to') }}: <strong>{{ $certificate->data['name'] ?? '' }}</strong> ({{ $certificate->data['student_code'] ?? $certificate->data['employee_code'] ?? '' }})</div>
                <div>{{ __('Issued on') }}: {{ $certificate->issued_on->format('d M Y') }}</div>
            </div>
        @endif
    </div>
</div>
@endsection
