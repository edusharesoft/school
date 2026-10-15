@extends('layouts.branch')
@section('title', __('Merit list'))

@section('content')
<x-page-header :title="__('Merit list').': '.$campaign->title" :breadcrumbs="[__('Admissions') => route('branch.admissions.campaigns.index'), __('Merit list') => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
        @can('admissions.approve')
            <form method="POST" action="{{ route('branch.admissions.merit.publish', $campaign) }}">@csrf
                <button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-globe me-1"></i>{{ $campaign->merit_published ? __('Hide public result') : __('Publish result') }}</button></form>
            <form method="POST" action="{{ route('branch.admissions.merit.promote', $campaign) }}" data-confirm="{{ __('Offer the next free seat to the first applicant on the waiting list?') }}">@csrf
                <button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-up me-1"></i>{{ __('Promote from waiting list') }}</button></form>
            <form method="POST" action="{{ route('branch.admissions.merit.generate', $campaign) }}" data-confirm="{{ __('Build the merit list now? Existing selected / waiting positions are recalculated.') }}">@csrf
                <button class="btn btn-brand btn-sm"><i class="fa-solid fa-ranking-star me-1"></i>{{ __('Generate merit list') }}</button></form>
        @endcan
    </x-slot:actions>
</x-page-header>

<p class="text-body-secondary small">{{ $campaign->schoolClass?->name }} · {{ $campaign->session?->name }} · {{ __('Seats') }}: {{ bn_digits($campaign->seats) }} ·
    {{ $campaign->has_test ? __('Ranked by test marks (ties: earlier application first); absent or below the pass mark are not selected.') : __('No test: first come, first served.') }}</p>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="dp green">
            <div class="dp-head"><h2><i class="fa-solid fa-check me-1"></i> {{ __('Selected') }} ({{ bn_digits($selected->count()) }})</h2></div>
            <div class="dp-body p-0"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>#</th><th>{{ __('Application no.') }}</th><th>{{ __('Applicant') }}</th>@if($campaign->has_test)<th>{{ __('Marks') }}</th>@endif<th>{{ __('Status') }}</th></tr></thead>
                <tbody>@foreach($selected as $a)
                    <tr><td>{{ bn_digits($a->merit_position) }}</td><td><a href="{{ route('branch.admissions.applications.show', $a) }}"><code>{{ $a->application_no }}</code></a></td><td>{{ $a->name }}</td>
                        @if($campaign->has_test)<td>{{ bn_digits($a->test_marks) }}</td>@endif<td><span class="badge text-bg-{{ $a->tone() }}">{{ __(ucfirst($a->status)) }}</span></td></tr>
                @endforeach</tbody></table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="dp orange">
            <div class="dp-head"><h2><i class="fa-solid fa-hourglass-half me-1"></i> {{ __('Waiting list') }} ({{ bn_digits($waiting->count()) }})</h2></div>
            <div class="dp-body p-0"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>#</th><th>{{ __('Application no.') }}</th><th>{{ __('Applicant') }}</th>@if($campaign->has_test)<th>{{ __('Marks') }}</th>@endif</tr></thead>
                <tbody>@foreach($waiting as $a)
                    <tr><td>{{ bn_digits($a->waiting_position) }}</td><td><a href="{{ route('branch.admissions.applications.show', $a) }}"><code>{{ $a->application_no }}</code></a></td><td>{{ $a->name }}</td>
                        @if($campaign->has_test)<td>{{ bn_digits($a->test_marks) }}</td>@endif</tr>
                @endforeach</tbody></table></div>
        </div>
    </div>
</div>
@endsection
