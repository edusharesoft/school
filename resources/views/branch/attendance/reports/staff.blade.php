@extends('layouts.branch')
@section('title', __('Monthly staff attendance'))

@section('content')
<x-page-header :title="__('Monthly attendance sheet')" :breadcrumbs="[__('Attendance') => route('branch.attendance.staff.index'), __('Monthly sheets') => null]">
    <x-slot:actions>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>{{ __('Export CSV') }}</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
    </x-slot:actions>
</x-page-header>

@include('branch.attendance.reports._tabs')

<form method="GET" class="panel panel-body mb-4 d-flex flex-wrap align-items-end gap-2 no-print">
    <div><label class="form-label small mb-1" for="month">{{ __('Month') }}</label>
        <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1" for="type">{{ __('Type') }}</label>
        <select id="type" name="type" class="form-select form-select-sm">
            <option value="">{{ __('Teachers & staff') }}</option>
            <option value="teacher" @selected($type === 'teacher')>{{ __('Teachers') }}</option>
            <option value="staff" @selected($type === 'staff')>{{ __('Other staff') }}</option>
        </select></div>
    <button class="btn btn-brand btn-sm"><i class="bi bi-table me-1"></i>{{ __('Show sheet') }}</button>
</form>

<div class="card">
    @if($sheet['rows']->isEmpty())
        <x-empty-state icon="person-badge" :title="__('No employees')" />
    @else
        <div class="card-header bg-transparent">{{ $month->translatedFormat('F Y') }} · {{ $currentBranch->name }}</div>
        @include('branch.attendance.reports._sheet', [
            'firstHead' => __('Employee'),
            'label' => fn ($row) => '<strong>'.e($row['employee']->name).'</strong> <code class="small">'.e($row['employee']->employee_code).'</code>',
            'extraHead' => '<th title="'.e(__('Late minutes')).'">'.e(__('Late')).'′</th><th title="'.e(__('Overtime minutes')).'">OT′</th>',
            'extra' => fn ($row) => '<td class="text-center">'.e(bn_digits($row['late_minutes'])).'</td><td class="text-center">'.e(bn_digits($row['overtime_minutes'])).'</td>',
        ])
    @endif
</div>
@endsection
