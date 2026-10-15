@extends('layouts.public')
@section('title', $campaign->title)

@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admission.index') }}">{{ __('Admission') }}</a></li><li class="breadcrumb-item active">{{ $campaign->title }}</li></ol></nav>
<div class="row g-4">
    <div class="col-lg-8">
        @if(! $accepting)
            <div class="alert alert-warning">{{ __('Applications for this admission are accepted from :from to :to.', ['from' => $campaign->starts_on->format('d M Y'), 'to' => $campaign->ends_on->format('d M Y')]) }}</div>
        @else
            <form method="POST" action="{{ route('admission.submit', $campaign) }}" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="d-none" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                @include('public.admission._fields')
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirm" required>
                    <label class="form-check-label" for="confirm">{{ __('I confirm the information is correct. False information cancels the application.') }}</label>
                </div>
                <button class="btn btn-brand btn-lg"><i class="fa-solid fa-paper-plane me-1"></i>{{ __('Submit application') }}</button>
            </form>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="dp blue">
            <div class="dp-head"><h2><i class="fa-solid fa-circle-info me-1"></i> {{ __('About this admission') }}</h2></div>
            <div class="dp-body small">
                <div><strong>{{ __('Class') }}:</strong> {{ $campaign->schoolClass?->name }}</div>
                <div><strong>{{ __('Session') }}:</strong> {{ $campaign->session?->name }}</div>
                <div><strong>{{ __('Last date') }}:</strong> {{ $campaign->ends_on->format('d M Y') }}</div>
                <div><strong>{{ __('Seats') }}:</strong> {{ bn_digits($campaign->seats) }}</div>
                @if($campaign->min_age || $campaign->max_age)<div><strong>{{ __('Age limit') }}:</strong> {{ bn_digits($campaign->min_age ?? 0) }}–{{ bn_digits($campaign->max_age ?? '—') }} {{ __('years') }}</div>@endif
                @if($campaign->has_test)<div><strong>{{ __('Admission test') }}:</strong> {{ $campaign->test_at?->format('d M Y, h:i A') ?? __('to be announced') }} {{ $campaign->test_venue }}</div>@endif
                @if($campaign->requiresFee())<div><strong>{{ __('Admission fee') }}:</strong> {{ money_bdt($campaign->admission_fee) }}</div>@endif
                @if($campaign->instructions)<hr><div style="white-space:pre-line">{{ $campaign->instructions }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
