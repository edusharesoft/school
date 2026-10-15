@extends('layouts.branch')
@section('title', $disposal->disposal_no)

@section('content')
<x-page-header :title="$disposal->disposal_no" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Disposals') => route('branch.assets.disposals.index'), $disposal->disposal_no => null]" />
<div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-body small">
        <div class="mb-3"><x-inv-status :status="$disposal->status" /> <span class="badge text-bg-light border">{{ __(ucfirst($disposal->type)) }}</span></div>
        <dl class="row mb-0">
            <dt class="col-5">{{ __('Asset') }}</dt><dd class="col-7"><a href="{{ route('branch.assets.register.show', $disposal->asset_id) }}">{{ $disposal->asset?->displayName() }}</a> <span class="text-body-secondary">{{ $disposal->asset?->code }}</span></dd>
            <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $disposal->date->format('d M Y') }}</dd>
            <dt class="col-5">{{ __('Cost') }}</dt><dd class="col-7">৳{{ number_format($disposal->cost, 2) }}</dd>
            <dt class="col-5">{{ __('Depreciation') }}</dt><dd class="col-7">৳{{ number_format($disposal->accumulated, 2) }}</dd>
            <dt class="col-5">{{ __('Book value') }}</dt><dd class="col-7">৳{{ number_format($disposal->book_value, 2) }}</dd>
            <dt class="col-5">{{ __('Received') }}</dt><dd class="col-7">৳{{ number_format($disposal->proceeds, 2) }}@if($disposal->account) <span class="text-body-secondary">→ {{ $disposal->account->label() }}</span>@endif</dd>
            <dt class="col-5 fw-semibold">{{ $disposal->gain_loss < 0 ? __('Loss') : __('Gain') }}</dt><dd class="col-7 fw-semibold @if($disposal->gain_loss < 0) text-danger @elseif($disposal->gain_loss > 0) text-success @endif">৳{{ number_format(abs($disposal->gain_loss), 2) }}</dd>
            @if($disposal->buyer)<dt class="col-5">{{ __('Buyer / recipient') }}</dt><dd class="col-7">{{ $disposal->buyer }}</dd>@endif
            @if($disposal->reason)<dt class="col-5">{{ __('Reason') }}</dt><dd class="col-7">{{ $disposal->reason }}</dd>@endif
            <dt class="col-5">{{ __('Posted by') }}</dt><dd class="col-7">{{ $disposal->disposer?->name }}</dd>
        </dl>
    </div>
    @if($entries->isNotEmpty())<ul class="list-group list-group-flush small border-top">@foreach($entries as $e)<li class="list-group-item d-flex justify-content-between"><span>{{ $e->entry_no }} <span class="text-body-secondary">{{ $e->narration }}</span></span><span>{{ number_format($e->amount, 2) }}</span></li>@endforeach</ul>@endif
    </div></div>
    @if($disposal->status === 'posted')@can('assets.approve')
    <div class="col-lg-5"><form method="POST" action="{{ route('branch.assets.disposals.reverse', $disposal) }}" class="card" data-confirm="{{ __('Reverse this disposal?') }}">@csrf
        <div class="card-body"><label class="form-label small" for="rv">{{ __('Reverse this disposal') }}</label><input id="rv" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason') }}" required maxlength="200"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reverse') }}</button>
            <div class="form-text">{{ __('The asset returns to the register as available; depreciation charged before the disposal stays.') }}</div></div></form></div>
    @endcan @endif
</div>
@endsection
