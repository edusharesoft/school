@extends('layouts.branch')
@section('title', __('Admissions'))

@section('content')
<x-page-header :title="__('Admission rounds')" :breadcrumbs="[$currentBranch->name => route('home.redirect'), __('Admissions') => null]">
    <x-slot:actions>
        <a href="{{ route('admission.index') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-globe me-1"></i>{{ __('Public admission page') }}</a>
        @can('admissions.create')
            <a href="{{ route('branch.admissions.campaigns.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New admission') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    @if($campaigns->isEmpty())
        <x-empty-state icon="person-vcard" :title="__('No admission rounds yet')" :message="__('Create one per class and session: dates, seats, test, fee and required documents.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Admission') }}</th><th>{{ __('Class') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Seats') }}</th><th>{{ __('Applications') }}</th><th>{{ __('Selected') }}</th><th>{{ __('Admitted') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($campaigns as $c)
                    @php($n = $counts[$c->id] ?? [])
                    <tr>
                        <td><a class="fw-semibold text-decoration-none" href="{{ route('branch.admissions.applications.index', ['campaign_id' => $c->id]) }}">{{ $c->title }}</a>
                            <div class="small text-body-secondary">{{ $c->session?->name }} · {{ $c->is_online ? __('Online + offline') : __('Offline only') }}@if($c->has_test) · {{ __('Test') }}@endif</div></td>
                        <td>{{ $c->schoolClass?->name }}</td>
                        <td class="small text-nowrap">{{ $c->starts_on->format('d M') }} – {{ $c->ends_on->format('d M Y') }}</td>
                        <td>{{ bn_digits($c->seats) }}</td>
                        <td>{{ bn_digits(array_sum($n)) }}</td>
                        <td>{{ bn_digits($n['selected'] ?? 0) }}</td>
                        <td>{{ bn_digits($n['admitted'] ?? 0) }}</td>
                        <td><span class="badge text-bg-{{ ['open' => 'success', 'closed' => 'secondary'][$c->status] ?? 'warning' }}">{{ __(ucfirst($c->status)) }}</span>
                            @if($c->merit_published)<span class="badge text-bg-info">{{ __('Result published') }}</span>@endif</td>
                        <td class="text-end text-nowrap">
                            @if($c->has_test)@can('admissions.update')<a href="{{ route('branch.admissions.merit.marks', $c) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Test marks') }}"><i class="fa-solid fa-pen"></i></a>@endcan @endif
                            <a href="{{ route('branch.admissions.merit.show', $c) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Merit list') }}"><i class="fa-solid fa-list-ol"></i></a>
                            @can('admissions.update')<a href="{{ route('branch.admissions.campaigns.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>@endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $campaigns->links() }}</div>
    @endif
</div>
@endsection
