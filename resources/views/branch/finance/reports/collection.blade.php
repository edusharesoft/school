@extends('layouts.branch')
@section('title', __('Collection report'))

@section('content')
<x-page-header :title="__('Collection report')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Reports') => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
        @can('financial-reports.export')
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
        @endcan
    </x-slot:actions>
</x-page-header>
@include('branch.finance.reports._nav')

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><select name="method" class="form-select form-select-sm" aria-label="{{ __('Method') }}"><option value="">{{ __('All methods') }}</option>
                @foreach(App\Models\Finance\FeePayment::METHODS as $m)<option value="{{ $m }}" @selected(request('method') === $m)>{{ __(ucfirst($m)) }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="account_id" class="form-select form-select-sm" aria-label="{{ __('Account') }}"><option value="">{{ __('All accounts') }}</option>
                @foreach($accounts as $id => $name)<option value="{{ $id }}" @selected((int) request('account_id') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="received_by" class="form-select form-select-sm" aria-label="{{ __('Collected by') }}"><option value="">{{ __('All collectors') }}</option>
                @foreach($collectors as $id => $name)<option value="{{ $id }}" @selected((int) request('received_by') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-auto"><button class="btn btn-sm btn-brand">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><x-stat-card :label="__('Received')" :value="'৳'.number_format($total, 2)" icon="fa-solid fa-sack-dollar" bg="#dcfce7" color="#15803d" :sub="$from->format('d M Y').' – '.$to->format('d M Y')" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Refunded')" :value="'৳'.number_format($refunded, 2)" icon="fa-solid fa-rotate-left" bg="#fef3c7" color="#b45309" /></div>
    <div class="col-md-4"><x-stat-card :label="__('Net collection')" :value="'৳'.number_format($net, 2)" icon="fa-solid fa-scale-balanced" bg="#e0e7ff" color="#4338ca" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-header bg-transparent fw-semibold">{{ __('By method') }}</div>
        <ul class="list-group list-group-flush small">@forelse($byMethod as $m => $amt)<li class="list-group-item d-flex"><span>{{ __(ucfirst($m)) }}</span><strong class="ms-auto">৳{{ number_format($amt, 2) }}</strong></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse</ul></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-header bg-transparent fw-semibold">{{ __('By collector') }}</div>
        <ul class="list-group list-group-flush small">@forelse($byCollector as $n => $amt)<li class="list-group-item d-flex"><span>{{ $n }}</span><strong class="ms-auto">৳{{ number_format($amt, 2) }}</strong></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse</ul></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-header bg-transparent fw-semibold">{{ __('By day') }}</div>
        <ul class="list-group list-group-flush small" style="max-height:240px;overflow:auto">@forelse($byDay as $d => $row)<li class="list-group-item d-flex"><span>{{ \Carbon\Carbon::parse($d)->format('d M Y') }} ({{ $row['count'] }})</span><strong class="ms-auto">৳{{ number_format($row['amount'], 2) }}</strong></li>@empty<li class="list-group-item text-body-secondary">—</li>@endforelse</ul></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Student') }}</th><th>{{ __('Method') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody>
            @forelse($payments as $p)
                <tr><td>{{ $p->date->format('d M Y') }}</td><td>{{ $p->receipt_no }}</td><td>{{ $p->student?->name }} <code>{{ $p->student?->student_code }}</code></td>
                    <td>{{ __(ucfirst($p->method)) }}</td><td>{{ $p->account?->name }}</td><td class="text-end">{{ number_format($p->amount, 2) }}</td><td>{{ $p->receiver?->name }}</td></tr>
            @empty
                <tr><td colspan="7" class="text-body-secondary">{{ __('No receipts in this period') }}</td></tr>
            @endforelse
            @foreach($refunds as $r)
                <tr class="table-warning"><td>{{ $r->date->format('d M Y') }}</td><td>{{ $r->refund_no }}</td><td>{{ $r->student?->name }}</td><td>{{ __('Refund') }}</td><td></td><td class="text-end">−{{ number_format($r->amount, 2) }}</td><td></td></tr>
            @endforeach
            </tbody>
            <tfoot><tr><th colspan="5" class="text-end">{{ __('Net collection') }}</th><th class="text-end">{{ number_format($net, 2) }}</th><th></th></tr></tfoot>
        </table>
    </div>
</div>
@endsection
