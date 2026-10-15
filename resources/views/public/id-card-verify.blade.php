@extends('layouts.public')
@section('title', __('Verify an ID card'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <form method="GET" class="dp blue mb-4">
            <div class="dp-head"><h2><i class="fa-solid fa-id-card me-1"></i> {{ __('Verify an ID card') }}</h2></div>
            <div class="dp-body d-flex gap-2">
                <input name="code" value="{{ $code }}" class="form-control text-uppercase" placeholder="{{ __('Code from the QR on the back of the card') }}" required maxlength="20" aria-label="{{ __('Verification code') }}">
                <button class="btn btn-brand">{{ __('Verify') }}</button>
            </div>
        </form>
        @if($code !== '' && ! $card)
            <div class="alert alert-danger"><i class="fa-solid fa-circle-xmark me-1"></i> {{ __('No ID card with this code was issued by this branch.') }}</div>
        @elseif($card)
            <div @class(['alert', 'alert-success' => $card->isValid(), 'alert-danger' => ! $card->isValid()])>
                <div class="fw-bold mb-1">
                    @if($card->isValid())<i class="fa-solid fa-circle-check me-1"></i> {{ __('Valid ID card') }}
                    @elseif($card->isActive())<i class="fa-solid fa-clock me-1"></i> {{ __('This card has expired.') }}
                    @else<i class="fa-solid fa-ban me-1"></i> {{ $card->status === 'replaced' ? __('This card was replaced by a newer card.') : __('This card is no longer valid (:status).', ['status' => __($card->status)]) }}@endif
                </div>
                <div>{{ $branch?->displayName() }} · <code>{{ $card->card_no }}</code></div>
                <div>{{ $card->holder_type === 'student' ? __('Student') : __('Staff') }}: <strong>{{ $card->holderName() }}</strong>@if($card->holder_type === 'employee' && $card->employee?->designation) — {{ $card->employee->designation->name }}@endif</div>
                <div>{{ __('Valid until') }}: {{ $card->valid_until->format('d M Y') }}</div>
            </div>
        @endif
    </div>
</div>
@endsection
