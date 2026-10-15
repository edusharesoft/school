@extends('layouts.central')
@section('title', __('Subscriptions'))

@section('content')
<x-page-header :title="__('Subscriptions')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Subscriptions') => null]">
    <x-slot:actions><a href="{{ route('central.plans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1"></i>{{ __('Plans') }}</a></x-slot:actions>
</x-page-header>
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="row g-2">
        <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search name') }}" aria-label="{{ __('Search') }}"></div>
        <div class="col-md-3"><select name="phase" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>
            @foreach(['trial' => __('Trial'), 'active' => __('Active'), 'grace' => __('Grace period'), 'expired' => __('Expired'), 'suspended' => __('Suspended'), 'none' => __('No plan')] as $k => $l)<option value="{{ $k }}" @selected($phase === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">{{ __('Filter') }}</button></div>
    </form></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>{{ __('Institution') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ends') }}</th><th>{{ __('Branches') }}</th><th>{{ __('Active students') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            @php($s = $r['subscription'])
            <tr>
                <td class="fw-semibold">{{ $r['institution']->name }}</td>
                <td>{{ $s?->plan?->displayName() ?? '—' }}</td>
                <td>@if($s)@include('central.saas.subscriptions._phase', ['phase' => $s->phase()])@else<span class="badge text-bg-light">{{ __('No plan') }}</span>@endif</td>
                <td>{{ $s?->endsOn()?->format('d M Y') ?? '—' }}</td>
                <td>{{ $r['usage']['branches'] }}@if($s?->plan?->max_branches) / {{ $s->plan->max_branches }}@endif</td>
                <td>{{ number_format($r['usage']['students']) }}@if($s?->plan?->max_students) / {{ number_format($s->plan->max_students) }}@endif</td>
                <td class="text-end"><a href="{{ route('central.subscriptions.show', $r['institution']) }}" class="btn btn-sm btn-outline-secondary">{{ __('Manage') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('Nothing to show.') }}</td></tr>
        @endforelse
        </tbody></table></div>
</div>
@endsection
