@extends('layouts.public')
@section('title', __('Application status'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <form method="GET" class="dp blue mb-4">
            <div class="dp-head"><h2><i class="fa-solid fa-magnifying-glass me-1"></i> {{ __('Check application status') }}</h2></div>
            <div class="dp-body row g-2">
                <div class="col-md-6"><label class="form-label small" for="application_no">{{ __('Application number') }}</label>
                    <input id="application_no" name="application_no" value="{{ request('application_no') }}" class="form-control text-uppercase" required maxlength="30"></div>
                <div class="col-md-6"><label class="form-label small" for="phone">{{ __('Mobile number given in the form') }}</label>
                    <input id="phone" name="phone" value="{{ request('phone') }}" class="form-control" required maxlength="20" inputmode="tel"></div>
                <div class="col-12"><button class="btn btn-brand w-100">{{ __('Check') }}</button></div>
            </div>
        </form>

        @if($searched && ! $application)
            <div class="alert alert-warning">{{ __('No application matches this number and mobile.') }}</div>
        @elseif($application)
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <h2 class="h5 mb-0 me-auto">{{ $application->name }}</h2>
                        <span class="badge text-bg-{{ $application->tone() }} fs-6">{{ $application->publicStatus() }}</span>
                    </div>
                    <div class="small text-body-secondary mb-3">{{ $application->application_no }} · {{ $application->campaign?->title }}</div>
                    @if($application->campaign?->has_test && $application->campaign?->test_at && in_array($application->status, ['submitted', 'verified'], true))
                        <p class="mb-1"><strong>{{ __('Admission test') }}:</strong> {{ $application->campaign->test_at->format('d M Y, h:i A') }} {{ $application->campaign->test_venue }}</p>
                    @endif
                    @if($application->campaign?->merit_published)
                        @if($application->merit_position && $application->status !== 'waiting')<p class="mb-1"><strong>{{ __('Merit position') }}:</strong> {{ bn_digits($application->merit_position) }}</p>@endif
                        @if($application->waiting_position)<p class="mb-1"><strong>{{ __('Waiting list position') }}:</strong> {{ bn_digits($application->waiting_position) }}</p>@endif
                    @endif
                    @if($application->status === 'selected')
                        <div class="alert alert-success mt-3 mb-0">{{ __('Congratulations! Please visit the office with the original documents to complete the admission.') }}
                            @if((float) $application->fee_amount > 0) {{ __('Admission fee') }}: {{ money_bdt($application->fee_amount) }}.@endif</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
