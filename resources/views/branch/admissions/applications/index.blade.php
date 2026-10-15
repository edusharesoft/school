@extends('layouts.branch')
@section('title', __('Admission applications'))

@section('content')
<x-page-header :title="__('Admission applications')" :breadcrumbs="[__('Admissions') => route('branch.admissions.campaigns.index'), __('Applications') => null]">
    <x-slot:actions>
        @if($campaign)
            <a href="{{ route('branch.admissions.merit.show', $campaign) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-list-ol me-1"></i>{{ __('Merit list') }}</a>
            @if($campaign->has_test)@can('admissions.update')<a href="{{ route('branch.admissions.merit.marks', $campaign) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i>{{ __('Test marks') }}</a>@endcan @endif
        @endif
        @can('admissions.create')
            <a href="{{ route('branch.admissions.applications.create', ['campaign_id' => $campaign?->id]) }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Offline application') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <select name="campaign_id" class="form-select form-select-sm" aria-label="{{ __('Admission') }}" onchange="this.form.submit()">
                    @foreach($campaigns as $c)<option value="{{ $c->id }}" @selected($campaign?->id === $c->id)>{{ $c->title }} ({{ $c->schoolClass?->name }})</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name, application no., mobile') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach(App\Models\Admissions\AdmissionApplication::STATUSES as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ __(ucfirst($st)) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="source" class="form-select form-select-sm" aria-label="{{ __('Source') }}">
                    <option value="">{{ __('Online & offline') }}</option>
                    <option value="online" @selected(request('source') === 'online')>{{ __('Online') }}</option>
                    <option value="offline" @selected(request('source') === 'offline')>{{ __('Offline') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button></div>
        </form>
    </div>
    @if($applications->isEmpty())
        <x-empty-state icon="inbox" :title="__('No applications')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Application no.') }}</th><th>{{ __('Applicant') }}</th><th>{{ __('Guardian mobile') }}</th><th>{{ __('Source') }}</th>
                    @if($campaign?->has_test)<th>{{ __('Marks') }}</th>@endif<th>{{ __('Merit') }}</th><th>{{ __('Fee') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($applications as $a)
                    <tr>
                        <td><code>{{ $a->application_no }}</code></td>
                        <td><div class="fw-semibold">{{ $a->name }}</div><div class="small text-body-secondary">{{ $a->date_of_birth->format('d M Y') }} · {{ __(ucfirst($a->gender)) }}</div></td>
                        <td>{{ $a->guardian_phone }}</td>
                        <td><span class="badge text-bg-light border">{{ __(ucfirst($a->source)) }}</span></td>
                        @if($campaign?->has_test)<td>{{ $a->test_absent ? __('Absent') : ($a->test_marks === null ? '—' : bn_digits($a->test_marks)) }}</td>@endif
                        <td>{{ $a->waiting_position ? __('W').bn_digits($a->waiting_position) : ($a->merit_position ? bn_digits($a->merit_position) : '—') }}</td>
                        <td>@if((float) $a->fee_amount > 0)<span class="badge text-bg-{{ $a->feeSettled() ? 'success' : 'warning' }}">{{ __(ucfirst($a->fee_status)) }}</span>@else — @endif</td>
                        <td><span class="badge text-bg-{{ $a->tone() }}">{{ __(ucfirst($a->status)) }}</span></td>
                        <td class="text-end"><a href="{{ route('branch.admissions.applications.show', $a) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Open') }}"><i class="bi bi-chevron-right"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex align-items-center"><span class="small text-body-secondary me-auto">{{ __('Total') }}: {{ bn_digits($applications->total()) }}</span>{{ $applications->links() }}</div>
    @endif
</div>
@endsection
