@extends('layouts.branch')
@section('title', __('Payroll'))

@section('content')
<x-page-header :title="__('Payroll')" :breadcrumbs="[__('Fees & accounts') => null, __('Payroll') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.finance.payroll.salaries') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-lines-fill me-1"></i>{{ __('Salary structures') }}</a>
    </x-slot:actions>
</x-page-header>

@if($withoutStructure > 0)
    <div class="alert alert-warning small">{{ __(':count active employee(s) have no salary structure and are left out of salary sheets.', ['count' => $withoutStructure]) }}
        <a href="{{ route('branch.finance.payroll.salaries') }}">{{ __('Set them up') }}</a></div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            @if($runs->isEmpty())
                <x-empty-state icon="people" :title="__('No salary sheets yet')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Employees') }}</th><th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Deductions') }}</th><th class="text-end">{{ __('Net pay') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @foreach($runs as $r)
                            <tr>
                                <td><a href="{{ route('branch.finance.payroll.show', $r) }}" class="fw-semibold text-decoration-none">{{ $r->monthLabel() }}</a>
                                    <div class="text-body-secondary">{{ $r->preparer?->name }}@if($r->approver) · {{ __('approved by') }} {{ $r->approver->name }}@endif</div></td>
                                <td class="text-end">{{ $r->employees }}</td>
                                <td class="text-end">{{ number_format($r->gross, 2) }}</td>
                                <td class="text-end">{{ number_format($r->deductions, 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($r->net, 2) }}</td>
                                <td><span class="badge text-bg-{{ $r->tone() }}">{{ __(ucfirst($r->status)) }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body">{{ $runs->links() }}</div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        @can('payroll.process')
            <form method="POST" action="{{ route('branch.finance.payroll.prepare') }}" class="card">
                @csrf
                <div class="card-header bg-transparent fw-semibold">{{ __('Prepare salary sheet') }}</div>
                <div class="card-body">
                    <x-form.input name="period" type="month" :label="__('Month')" :value="$period" required col="col-12" />
                    <div class="small text-body-secondary mt-2">{{ __('Uses each employee\'s salary structure. Absent days without leave and leave without pay are deducted when the fee settings say so.') }}</div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-brand w-100">{{ __('Prepare') }}</button></div>
            </form>
        @endcan
    </div>
</div>
@endsection
