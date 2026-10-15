@extends('layouts.branch')
@section('title', __('Expenses'))

@section('content')
<x-page-header :title="__('Expenses')" :breadcrumbs="[__('Fees & accounts') => null, __('Expenses') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.finance.vendors.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shop me-1"></i>{{ __('Suppliers') }}</a>
        @can('expenses.create')<a href="{{ route('branch.finance.expenses.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New expense') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-md-6"><x-stat-card :label="__('Approved expenses in the period')" :value="'৳'.number_format($total, 2)" icon="fa-solid fa-money-bill-trend-up" bg="#fee2e2" color="#b91c1c" :sub="$from->format('d M Y').' – '.$to->format('d M Y')" /></div>
    <div class="col-md-6"><x-stat-card :label="__('Waiting for approval')" :value="$pending" icon="fa-solid fa-hourglass-half" bg="#fef3c7" color="#b45309" :href="route('branch.finance.expenses.index', ['status' => 'pending', 'from' => now()->subYear()->toDateString()])" /></div>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>
                @foreach(App\Models\Finance\Expense::STATUSES as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ __(ucfirst($st)) }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="account_id" class="form-select form-select-sm" aria-label="{{ __('Account') }}"><option value="">{{ __('All expense accounts') }}</option>
                @foreach($accounts as $id => $name)<option value="{{ $id }}" @selected((int) request('account_id') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($expenses->isEmpty())
        <x-empty-state icon="wallet2" :title="__('No expenses in this period')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead><tr><th>{{ __('Voucher') }}</th><th>{{ __('Date') }}</th><th>{{ __('Account') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($expenses as $e)
                    <tr>
                        <td><a href="{{ route('branch.finance.expenses.show', $e) }}" class="fw-semibold text-decoration-none">{{ $e->expense_no }}</a><div class="text-body-secondary">{{ $e->requester?->name }}</div></td>
                        <td class="text-nowrap">{{ $e->date->format('d M Y') }}</td>
                        <td>{{ $e->account?->name }}</td>
                        <td>{{ $e->description }}@if($e->vendor)<div class="text-body-secondary">{{ $e->vendor->name }}</div>@endif</td>
                        <td class="text-end fw-semibold">{{ number_format($e->amount, 2) }}</td>
                        <td><span class="badge text-bg-{{ $e->tone() }}">{{ __(ucfirst($e->status)) }}</span>@if($e->payment_mode === 'credit' && $e->status === 'approved')<div class="text-body-secondary">{{ __('unpaid') }}</div>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $expenses->links() }}</div>
    @endif
</div>
@endsection
