@extends('layouts.public')
@section('title', __('Admission'))

@section('content')
<div class="text-center mb-4">
    <h1 class="h3 fw-bold">{{ __('Online admission') }}</h1>
    <p class="text-body-secondary mb-0">{{ __('Apply online, then use your application number to check the status and result.') }}</p>
</div>

<div class="row g-4">
    @forelse($campaigns as $c)
        <div class="col-md-6 col-xl-4">
            <div class="dp green h-100">
                <div class="dp-head"><h2><i class="fa-solid fa-id-card me-1"></i> {{ $c->title }}</h2></div>
                <div class="dp-body small">
                    <div class="mb-1"><strong>{{ __('Class') }}:</strong> {{ $c->schoolClass?->name }} · <strong>{{ __('Session') }}:</strong> {{ $c->session?->name }}</div>
                    <div class="mb-1"><strong>{{ __('Last date') }}:</strong> {{ $c->ends_on->format('d M Y') }} · <strong>{{ __('Seats') }}:</strong> {{ bn_digits($c->seats) }}</div>
                    @if($c->has_test)<div class="mb-1"><strong>{{ __('Admission test') }}:</strong> {{ $c->test_at?->format('d M Y, h:i A') ?? __('to be announced') }}</div>@endif
                    @if($c->requiresFee())<div class="mb-1"><strong>{{ __('Admission fee') }}:</strong> {{ money_bdt($c->admission_fee) }}</div>@endif
                    @if($c->instructions)<p class="text-body-secondary mt-2 mb-0" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($c->instructions, 220) }}</p>@endif
                </div>
                <div class="p-3 pt-0"><a href="{{ route('admission.form', $c) }}" class="btn btn-brand w-100"><i class="fa-solid fa-pen-to-square me-1"></i>{{ __('Apply now') }}</a></div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><x-empty-state icon="calendar-x" :title="__('No admission is open right now')" :message="__('Please check again later or contact the office.')" /></div></div>
    @endforelse
</div>

@if($published->isNotEmpty())
    <h2 class="h5 mt-5 mb-3"><i class="fa-solid fa-list-ol me-1 text-primary"></i> {{ __('Published results') }}</h2>
    <div class="list-group">
        @foreach($published as $c)
            <a class="list-group-item list-group-item-action d-flex" href="{{ route('admission.merit', $c) }}">
                <span class="me-auto">{{ $c->title }} — {{ $c->schoolClass?->name }}</span><i class="bi bi-chevron-right"></i></a>
        @endforeach
    </div>
@endif
@endsection
