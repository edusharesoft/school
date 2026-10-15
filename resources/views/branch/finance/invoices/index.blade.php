@extends('layouts.branch')
@section('title', __('Fee invoices'))

@section('content')
<x-page-header :title="__('Fee invoices')" :breadcrumbs="[__('Fees & accounts') => null, __('Invoices') => null]">
    <x-slot:actions>
        @can('payments.collect')
            <a href="{{ route('branch.finance.students.search') }}" class="btn btn-success btn-sm"><i class="fa-solid fa-hand-holding-dollar me-1"></i>{{ __('Collect fees') }}</a>
        @endcan
        @can('invoices.generate')
            <a href="{{ route('branch.finance.invoices.generate') }}" class="btn btn-brand btn-sm"><i class="bi bi-lightning-charge me-1"></i>{{ __('Generate monthly invoices') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-md-4"><x-stat-card :label="__('Billed')" :value="'৳'.number_format((float) $totals->total, 2)" icon="fa-solid fa-file-invoice" bg="#e0e7ff" color="#4338ca" :sub="__('matching filter, excl. cancelled')" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Paid')" :value="'৳'.number_format((float) $totals->paid, 2)" icon="fa-solid fa-circle-check" bg="#dcfce7" color="#15803d" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Outstanding')" :value="'৳'.number_format((float) $totals->balance, 2)" icon="fa-solid fa-hourglass-half" bg="#fee2e2" color="#b91c1c" /></div>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Invoice no., student ID or name') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-2"><input type="month" name="period" value="{{ request('period') }}" class="form-control form-control-sm" aria-label="{{ __('Month') }}"></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['unpaid', 'partial', 'overdue', 'paid', 'cancelled'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach
            </select></div>
            <div class="col-md-2"><select name="class_id" class="form-select form-select-sm" aria-label="{{ __('Class') }}" onchange="this.form.submit()">
                <option value="">{{ __('All classes') }}</option>
                @foreach($classes as $id => $name)<option value="{{ $id }}" @selected((int) request('class_id') === $id)>{{ $name }}</option>@endforeach
            </select></div>
            @if($sections->isNotEmpty())
                <div class="col-md-2"><select name="section_id" class="form-select form-select-sm" aria-label="{{ __('Section') }}">
                    <option value="">{{ __('All sections') }}</option>
                    @foreach($sections as $id => $name)<option value="{{ $id }}" @selected((int) request('section_id') === $id)>{{ $name }}</option>@endforeach
                </select></div>
            @endif
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($invoices->isEmpty())
        <x-empty-state icon="receipt" :title="__('No invoices found')" :message="__('Set up fee heads and the class fee structure, then generate the monthly invoices.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Balance') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($invoices as $inv)
                    <tr>
                        <td><a href="{{ route('branch.finance.invoices.show', $inv) }}" class="fw-semibold text-decoration-none">{{ $inv->invoice_no }}</a><div class="small text-body-secondary">{{ $inv->title }}</div></td>
                        <td><a href="{{ route('branch.finance.students.show', $inv->student_id) }}" class="text-decoration-none">{{ $inv->student?->name }}</a><div class="small text-body-secondary">{{ $inv->student?->student_code }}</div></td>
                        <td class="small">{{ $inv->schoolClass?->name }} {{ $inv->section?->name }}</td>
                        <td class="small text-nowrap @if($inv->isOpen() && $inv->due_date->isPast()) text-danger fw-semibold @endif">{{ $inv->due_date->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format($inv->total, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($inv->balance, 2) }}</td>
                        <td><span class="badge text-bg-{{ $inv->tone() }}">{{ __(ucfirst($inv->status)) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $invoices->links() }}</div>
    @endif
</div>
@endsection
