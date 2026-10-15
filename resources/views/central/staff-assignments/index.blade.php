@extends('layouts.central')
@section('title', __('Staff branch assignments'))

@section('content')
<x-page-header :title="__('Staff branch assignments')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Staff assignments') => null]" />

<div class="alert alert-light border small"><i class="fa-solid fa-circle-info text-primary me-1"></i>
    {{ __('A teacher or employee keeps one home branch (HR record, salary, attendance). An assignment lets them also work in another branch; their login gets access to that branch until the assignment ends.') }}</div>

@can('employees.update')
<form method="POST" action="{{ route('central.staff-assignments.store') }}" class="card mb-4">
    @csrf
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small" for="employee_id">{{ __('Employee') }}</label>
            <select id="employee_id" name="employee_id" class="form-select form-select-sm" required>
                <option value="">—</option>
                @foreach($employees as $e)<option value="{{ $e->id }}" @selected((int) old('employee_id') === $e->id)>{{ $e->name }} ({{ $e->employee_code }})</option>@endforeach
            </select></div>
        <div class="col-md-3"><label class="form-label small" for="branch_id">{{ __('Additional branch') }}</label>
            <select id="branch_id" name="branch_id" class="form-select form-select-sm" required>
                <option value="">—</option>
                @foreach($branches as $id => $name)<option value="{{ $id }}" @selected((int) old('branch_id') === $id)>{{ $name }}</option>@endforeach
            </select></div>
        <div class="col-md-2"><label class="form-label small" for="starts_on">{{ __('From') }}</label>
            <input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', now()->toDateString()) }}" class="form-control form-control-sm" required></div>
        <div class="col-md-2"><label class="form-label small" for="ends_on">{{ __('To (optional)') }}</label>
            <input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on') }}" class="form-control form-control-sm"></div>
        <div class="col-md-9"><input name="duty" value="{{ old('duty') }}" class="form-control form-control-sm" maxlength="191" placeholder="{{ __('Duty, e.g. Physics classes XI–XII, Sunday & Tuesday') }}" aria-label="{{ __('Duty') }}"></div>
        <div class="col-md-3"><button class="btn btn-brand btn-sm w-100">{{ __('Assign') }}</button></div>
    </div>
</form>
@endcan

<div class="card">
    <div class="card-body border-bottom d-flex gap-2">
        <a href="{{ route('central.staff-assignments.index') }}" @class(['btn btn-sm', 'btn-brand' => request('show') !== 'all', 'btn-outline-secondary' => request('show') === 'all'])>{{ __('Current') }}</a>
        <a href="{{ route('central.staff-assignments.index', ['show' => 'all']) }}" @class(['btn btn-sm', 'btn-brand' => request('show') === 'all', 'btn-outline-secondary' => request('show') !== 'all'])>{{ __('All') }}</a>
    </div>
    @if($assignments->isEmpty())
        <x-empty-state icon="people" :title="__('No staff assignments')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Home branch') }}</th><th>{{ __('Also works at') }}</th><th>{{ __('Duty') }}</th><th>{{ __('Period') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($assignments as $a)
                    <tr>
                        <td><div class="fw-medium">{{ $a->employee?->name }}</div><code class="small">{{ $a->employee?->employee_code }}</code></td>
                        <td>{{ $a->employee?->branch?->name }}</td>
                        <td class="fw-semibold">{{ $a->branch?->name }}</td>
                        <td class="small">{{ $a->duty ?: '—' }}</td>
                        <td class="small text-nowrap">{{ $a->starts_on->format('d M Y') }} – {{ $a->ends_on?->format('d M Y') ?? __('open') }}</td>
                        <td class="text-end">
                            @if($a->isCurrent())@can('employees.update')
                                <form method="POST" action="{{ route('central.staff-assignments.end', $a) }}" data-confirm="{{ __('End this assignment today?') }}">@csrf
                                    <button class="btn btn-sm btn-outline-danger">{{ __('End') }}</button></form>
                            @endcan @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $assignments->links() }}</div>
    @endif
</div>
@endsection
