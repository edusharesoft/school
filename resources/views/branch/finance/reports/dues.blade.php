@extends('layouts.branch')
@section('title', __('Outstanding dues'))

@section('content')
<x-page-header :title="__('Outstanding dues')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Reports') => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
        @can('financial-reports.export')<a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>CSV</a>@endcan
    </x-slot:actions>
</x-page-header>
@include('branch.finance.reports._nav')

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Class') }}"><option value="">{{ __('All classes') }}</option>
                @foreach($classes as $id => $name)<option value="{{ $id }}" @selected($filters['class_id'] === $id)>{{ $name }}</option>@endforeach</select></div>
            @if($sections->isNotEmpty())
                <div class="col-md-2"><select name="section_id" class="form-select form-select-sm" aria-label="{{ __('Section') }}"><option value="">{{ __('All sections') }}</option>
                    @foreach($sections as $id => $name)<option value="{{ $id }}" @selected($filters['section_id'] === $id)>{{ $name }}</option>@endforeach</select></div>
            @endif
            <div class="col-auto form-check ms-2"><input class="form-check-input" type="checkbox" name="overdue" value="1" id="f_od" @checked($filters['overdue'])><label class="form-check-label small" for="f_od">{{ __('Overdue only') }}</label></div>
            <div class="col-auto"><button class="btn btn-sm btn-brand">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body border-bottom small d-flex flex-wrap gap-4">
        <span>{{ __('Students with dues') }}: <strong>{{ $rows->count() }}</strong></span>
        <span>{{ __('Total due') }}: <strong class="text-danger">৳{{ number_format($rows->sum('balance'), 2) }}</strong></span>
        <span>{{ __('Overdue more than 90 days') }}: <strong>৳{{ number_format($rows->sum(fn ($r) => $r['buckets']['90_plus']), 2) }}</strong></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 small">
            <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Mobile') }}</th><th class="text-end">{{ __('Not yet due') }}</th><th class="text-end">1–30</th><th class="text-end">31–60</th><th class="text-end">61–90</th><th class="text-end">90+</th><th class="text-end">{{ __('Total due') }}</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td><a href="{{ route('branch.finance.students.show', $r['student']) }}">{{ $r['student']?->name }}</a> <code>{{ $r['student']?->student_code }}</code><div class="text-body-secondary">{{ trans_choice(':count invoice|:count invoices', $r['invoices'], ['count' => $r['invoices']]) }} · {{ __('oldest due') }} {{ \Carbon\Carbon::parse($r['oldest_due'])->format('d M Y') }}</div></td>
                    <td>{{ $r['class'] }} {{ $r['section'] }}</td>
                    <td>{{ $r['student']?->phone }}</td>
                    @foreach(['current', '1_30', '31_60', '61_90', '90_plus'] as $b)<td class="text-end">{{ $r['buckets'][$b] > 0 ? number_format($r['buckets'][$b], 2) : '' }}</td>@endforeach
                    <td class="text-end fw-semibold">{{ number_format($r['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-body-secondary">{{ __('No outstanding dues.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent small text-body-secondary">{{ __('Aging columns: days past the due date.') }}</div>
</div>
@endsection
