@extends('layouts.branch')
@section('title', __('Receipts'))

@section('content')
<x-page-header :title="__('Receipts')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Receipts') => null]">
    <x-slot:actions>
        @can('payments.collect')<a href="{{ route('branch.finance.students.search') }}" class="btn btn-success btn-sm"><i class="fa-solid fa-hand-holding-dollar me-1"></i>{{ __('Collect fees') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><select name="method" class="form-select form-select-sm" aria-label="{{ __('Method') }}"><option value="">{{ __('All methods') }}</option>
                @foreach(App\Models\Finance\FeePayment::METHODS as $m)<option value="{{ $m }}" @selected(request('method') === $m)>{{ __(ucfirst($m)) }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All') }}</option>
                <option value="completed" @selected(request('status') === 'completed')>{{ __('Completed') }}</option><option value="reversed" @selected(request('status') === 'reversed')>{{ __('Reversed') }}</option></select></div>
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Receipt, reference or student') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    <div class="card-body border-bottom small">{{ __('Total received in the period') }}: <strong class="fs-6">৳{{ number_format($total, 2) }}</strong></div>
    @if($payments->isEmpty())
        <x-empty-state icon="cash-coin" :title="__('No receipts in this period')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Student') }}</th><th>{{ __('Method') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('By') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($payments as $p)
                    <tr @class(['text-body-tertiary text-decoration-line-through' => $p->isReversed()])>
                        <td class="fw-semibold">{{ $p->receipt_no }}</td>
                        <td>{{ $p->date->format('d M Y') }}</td>
                        <td><a href="{{ route('branch.finance.students.show', $p->student_id) }}">{{ $p->student?->name }}</a> <code>{{ $p->student?->student_code }}</code></td>
                        <td>{{ __(ucfirst($p->method)) }}@if($p->reference)<div class="text-body-secondary">{{ $p->reference }}</div>@endif</td>
                        <td>{{ $p->account?->name }}</td>
                        <td class="text-end fw-semibold">{{ number_format($p->amount, 2) }}</td>
                        <td>{{ $p->receiver?->name }}</td>
                        <td class="text-end"><a href="{{ route('branch.finance.payments.show', $p) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Print receipt') }}"><i class="bi bi-printer"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
