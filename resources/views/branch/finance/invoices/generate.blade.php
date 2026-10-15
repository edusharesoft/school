@extends('layouts.branch')
@section('title', __('Generate monthly invoices'))

@section('content')
<x-page-header :title="__('Generate monthly invoices')" :breadcrumbs="[__('Invoices') => route('branch.finance.invoices.index'), __('Generate') => null]" />

<div class="row g-3">
    <div class="col-lg-6">
        <form method="POST" action="{{ route('branch.finance.invoices.generate.run') }}" class="card" data-confirm="{{ __('Generate invoices for the selected month?') }}">
            @csrf
            <div class="card-body row g-3">
                <x-form.input name="period" type="month" :label="__('Month')" :value="$period" required col="col-md-6" />
                <x-form.select name="school_class_id" :label="__('Class')" :options="$classes->all()" :placeholder="__('All classes')" col="col-md-6" />
                <div class="col-12 small text-body-secondary">
                    {{ __('One invoice per enrolled student with every fee billed in that month (fee structure + student extra fees − discounts, scholarships and sibling discount). Students who already have that month\'s invoice are skipped, so running it again is safe. Advance credit is applied automatically.') }}
                </div>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-brand"><i class="bi bi-lightning-charge me-1"></i>{{ __('Generate') }}</button></div>
        </form>

        <form method="POST" action="{{ route('branch.finance.invoices.late-fees') }}" class="card mt-3" data-confirm="{{ __('Charge the late fee on overdue invoices now?') }}">
            @csrf
            <div class="card-body small">{{ __('Late fees are charged every night automatically according to the fee settings. You can also run it now; an invoice is never charged twice.') }}</div>
            <div class="card-footer bg-transparent d-flex gap-2"><button class="btn btn-outline-warning btn-sm">{{ __('Charge late fees now') }}</button>
                <a href="{{ route('branch.finance.settings') }}" class="btn btn-link btn-sm">{{ __('Fee settings') }}</a></div>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-transparent fw-semibold">{{ __('Generated months') }}</div>
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Invoices') }}</th><th class="text-end">{{ __('Total (৳)') }}</th></tr></thead>
                <tbody>
                @forelse($recent as $r)
                    <tr><td><a href="{{ route('branch.finance.invoices.index', ['period' => $r->period]) }}">{{ \Carbon\Carbon::createFromFormat('Y-m', $r->period)->format('F Y') }}</a></td><td class="text-end">{{ $r->n }}</td><td class="text-end">{{ number_format((float) $r->total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-body-secondary">{{ __('Nothing generated yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
