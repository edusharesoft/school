@extends('layouts.branch')
@section('title', $overdueOnly ? __('Overdue books') : __('Loans'))

@section('content')
<x-page-header :title="$overdueOnly ? __('Overdue books') : __('Loans')" :breadcrumbs="[__('Library') => route('branch.library.index'), __('Loans') => null]" />
<div class="card">
    <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3"><select name="overdue" class="form-select form-select-sm" aria-label="{{ __('Filter') }}" onchange="this.form.submit()">
            <option value="">{{ __('Books out now') }}</option><option value="1" @selected($overdueOnly)>{{ __('Overdue only') }}</option></select></div>
        <div class="col-md-3"><select name="member_type" class="form-select form-select-sm" aria-label="{{ __('Member type') }}"><option value="">{{ __('Students & staff') }}</option>
            <option value="student" @selected(request('member_type') === 'student')>{{ __('Students') }}</option><option value="employee" @selected(request('member_type') === 'employee')>{{ __('Staff') }}</option></select></div>
        <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Book title or accession no.') }}" aria-label="{{ __('Search') }}"></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
    </form></div>
    @if($loans->isEmpty())<x-empty-state icon="hand-thumbs-up" :title="__('Nothing to show')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Book') }}</th><th>{{ __('Member') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Fine so far') }}</th></tr></thead>
        <tbody>@foreach($loans as $loan)
            <tr>
                <td>{{ $loan->book?->displayTitle() }}<div class="text-body-secondary">{{ $loan->copy?->accession_no }}</div></td>
                <td><a href="{{ route('branch.library.member', [$loan->member_type, $loan->member_id]) }}">{{ $loan->memberModel()?->name ?? '—' }}</a><div class="text-body-secondary">{{ __(ucfirst($loan->member_type)) }}</div></td>
                <td>{{ $loan->issued_on->format('d M Y') }}</td>
                <td class="{{ $loan->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $loan->due_on->format('d M Y') }}@if($loan->status !== 'issued') <span class="badge text-bg-secondary">{{ __(ucfirst($loan->status)) }}</span>@endif</td>
                <td class="text-end">{{ $loan->isOverdue() ? '৳'.number_format($library->accruedFine($loan), 2) : '—' }}</td>
            </tr>
        @endforeach</tbody>
    </table></div><div class="card-footer bg-transparent">{{ $loans->links() }}</div>@endif
</div>
@endsection
