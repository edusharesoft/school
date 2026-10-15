@extends('layouts.public')
@section('title', __('Application submitted'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="dp green text-center">
            <div class="dp-body py-5">
                <i class="fa-solid fa-circle-check text-success" style="font-size:56px"></i>
                <h1 class="h4 mt-3">{{ __('Application submitted') }}</h1>
                <p class="mb-1">{{ $info['name'] }} · {{ $info['campaign'] }}</p>
                <div class="my-3"><span class="text-body-secondary small d-block">{{ __('Your application number') }}</span>
                    <span class="fs-3 fw-bold user-select-all">{{ $info['application_no'] }}</span></div>
                <p class="small text-body-secondary">{{ __('Write this number down. You need it and your mobile number to check the status.') }}</p>
                @if($info['test_at'])<p class="small"><strong>{{ __('Admission test') }}:</strong> {{ $info['test_at'] }} {{ $info['test_venue'] }}</p>@endif
                @if($info['fee'] > 0)<p class="small"><strong>{{ __('Admission fee') }}:</strong> {{ money_bdt($info['fee']) }} — {{ __('payable at the office after selection.') }}</p>@endif
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
                    <a href="{{ route('admission.status', ['application_no' => $info['application_no']]) }}" class="btn btn-brand">{{ __('Check status') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
