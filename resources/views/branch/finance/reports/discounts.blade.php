@extends('layouts.branch')
@section('title', __('Discounts & waivers'))

@section('content')
<x-page-header :title="__('Discounts & waivers')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Reports') => null]">
    <x-slot:actions><button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button></x-slot:actions>
</x-page-header>
@include('branch.finance.reports._nav')

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small" for="f_from">{{ __('Invoices issued from') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-auto"><button class="btn btn-sm btn-brand">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6"><x-stat-card :label="__('Discounts & scholarships')" :value="'৳'.number_format($discountTotal, 2)" icon="fa-solid fa-percent" bg="#dcfce7" color="#15803d" /></div>
    <div class="col-md-6"><x-stat-card :label="__('Waived')" :value="'৳'.number_format($waivedTotal, 2)" icon="fa-solid fa-hand-holding-heart" bg="#fef3c7" color="#b45309" /></div>
</div>

<div class="card mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('Discount lines') }}</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Student') }}</th><th>{{ __('Fee') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Discount') }}</th></tr></thead>
            <tbody>
            @forelse($items as $i)
                <tr><td>{{ $i->invoice_no }}</td><td>{{ $students->get($i->student_id)?->name }} <code>{{ $students->get($i->student_id)?->student_code }}</code></td>
                    <td>{{ $i->description }}</td><td>{{ $i->discount_note }}</td><td class="text-end">{{ number_format($i->amount, 2) }}</td><td class="text-end">{{ number_format($i->discount, 2) }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-body-secondary">{{ __('No discounts in this period.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-transparent fw-semibold">{{ __('Waivers') }}</div>
    <ul class="list-group list-group-flush small">
        @forelse($waivers as $w)
            <li class="list-group-item d-flex"><span>{{ $w->invoice_no }} — {{ $w->student?->name }}</span><strong class="ms-auto">৳{{ number_format($w->waived, 2) }}</strong></li>
        @empty
            <li class="list-group-item text-body-secondary">{{ __('No waivers in this period.') }}</li>
        @endforelse
    </ul>
</div>
@endsection
