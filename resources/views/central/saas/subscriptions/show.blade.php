@extends('layouts.central')
@section('title', __('Subscription').' · '.$institution->name)

@section('content')
<x-page-header :title="$institution->name" :breadcrumbs="[__('Subscriptions') => route('central.subscriptions.index'), $institution->name => null]" />
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-header fw-semibold">{{ __('Plan') }}
            @if($subscription) <span class="ms-2">@include('central.saas.subscriptions._phase', ['phase' => $subscription->phase()])</span> @endif</div>
        <form method="POST" action="{{ route('central.subscriptions.update', $institution) }}" class="card-body row g-3">@csrf @method('PUT')
            <div class="col-md-6"><label class="form-label" for="plan_id">{{ __('Plan') }} *</label><select id="plan_id" name="plan_id" class="form-select" required>
                @foreach($plans as $p)<option value="{{ $p->id }}" @selected(old('plan_id', $subscription?->plan_id) == $p->id)>{{ $p->displayName() }}@unless($p->is_active) ({{ __('Inactive') }})@endunless</option>@endforeach</select>@error('plan_id')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label" for="billing_cycle">{{ __('Billing') }}</label><select id="billing_cycle" name="billing_cycle" class="form-select">
                @foreach(['monthly' => __('Monthly'), 'yearly' => __('Yearly')] as $k => $l)<option value="{{ $k }}" @selected(old('billing_cycle', $subscription?->billing_cycle ?? 'monthly') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="trial_ends_on">{{ __('Trial ends') }}</label><input id="trial_ends_on" type="date" name="trial_ends_on" class="form-control" value="{{ old('trial_ends_on', $subscription?->trial_ends_on?->toDateString()) }}"></div>
            <div class="col-12"><label class="form-label" for="notes">{{ __('Notes') }}</label><input id="notes" name="notes" class="form-control" value="{{ old('notes', $subscription?->notes) }}" maxlength="1000"></div>
            <div class="col-12 text-end"><button class="btn btn-brand">{{ $subscription ? __('Save') : __('Start trial on this plan') }}</button></div>
        </form></div>

        @if($subscription)
        <div class="card"><div class="card-header fw-semibold">{{ __('Payments') }}</div>
            <form method="POST" action="{{ route('central.subscriptions.payment', $institution) }}" class="card-body row g-2 border-bottom">@csrf
                <div class="col-md-3"><label class="form-label small" for="amount">{{ __('Amount') }}</label><input id="amount" type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" required value="{{ old('amount', $subscription->billing_cycle === 'yearly' ? $subscription->plan->price_yearly : $subscription->plan->price_monthly) }}"></div>
                <div class="col-md-2"><label class="form-label small" for="periods">{{ $subscription->billing_cycle === 'yearly' ? __('Years') : __('Months') }}</label><input id="periods" type="number" min="1" max="36" name="periods" class="form-control form-control-sm" required value="1"></div>
                <div class="col-md-2"><label class="form-label small" for="method">{{ __('Method') }}</label><select id="method" name="method" class="form-select form-select-sm">@foreach(['bank' => __('Bank'), 'cash' => __('Cash'), 'bkash' => 'bKash', 'nagad' => 'Nagad', 'other' => __('Other')] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label small" for="reference">{{ __('Reference') }}</label><input id="reference" name="reference" class="form-control form-control-sm" maxlength="100"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-brand w-100">{{ __('Record') }}</button></div>
                @error('periods')<div class="text-danger small">{{ $message }}</div>@enderror
            </form>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Method') }}</th><th>{{ __('Period') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>@forelse($subscription->payments as $pay)
                    <tr><td>{{ $pay->paid_on->format('d M Y') }}</td><td class="text-end">{{ number_format($pay->amount, 2) }}</td><td>{{ $pay->method }}@if($pay->reference) <span class="text-body-secondary small">· {{ $pay->reference }}</span>@endif</td><td>{{ $pay->period_from->format('d M Y') }} → {{ $pay->period_to->format('d M Y') }}</td><td>{{ $pay->recorder?->name }}</td></tr>
                @empty<tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No payments yet.') }}</td></tr>@endforelse</tbody></table></div>
        </div>
        @endif
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header fw-semibold">{{ __('Usage') }}</div><div class="card-body">
            @php($plan = $subscription?->plan)
            <div class="d-flex justify-content-between"><span>{{ __('Branches') }}</span><strong>{{ $usage['branches'] }} / {{ $plan?->limitLabel($plan->max_branches) ?? __('Unlimited') }}</strong></div>
            <div class="d-flex justify-content-between"><span>{{ __('Active students') }}</span><strong>{{ number_format($usage['students']) }} / {{ $plan?->limitLabel($plan->max_students) ?? __('Unlimited') }}</strong></div>
            @if($subscription)
                <hr><div class="d-flex justify-content-between"><span>{{ __('Ends') }}</span><strong>{{ $subscription->endsOn()?->format('d M Y') ?? '—' }}</strong></div>
                @if(($left = $subscription->daysLeft()) !== null)<div class="small text-body-secondary">{{ $left >= 0 ? __(':n day(s) left', ['n' => $left]) : __('Ended :n day(s) ago', ['n' => abs($left)]) }}</div>@endif
            @endif
        </div></div>
        @if($subscription)
        <div class="card"><div class="card-header fw-semibold">{{ __('Actions') }}</div><div class="card-body d-grid gap-2">
            @if($subscription->status === 'suspended')
                <form method="POST" action="{{ route('central.subscriptions.action', [$institution, 'reactivate']) }}">@csrf<button class="btn btn-success w-100">{{ __('Reactivate') }}</button></form>
            @else
                <form method="POST" action="{{ route('central.subscriptions.action', [$institution, 'suspend']) }}" onsubmit="return confirm('{{ __('Suspend? Users can view but not change data.') }}')">@csrf<button class="btn btn-outline-danger w-100">{{ __('Suspend (read-only)') }}</button></form>
                @if($subscription->status !== 'cancelled')<form method="POST" action="{{ route('central.subscriptions.action', [$institution, 'cancel']) }}" onsubmit="return confirm('{{ __('Cancel the subscription? It keeps working until the paid period ends.') }}')">@csrf<button class="btn btn-outline-secondary w-100">{{ __('Cancel at period end') }}</button></form>@endif
            @endif
        </div></div>
        @endif
    </div>
</div>
@endsection
