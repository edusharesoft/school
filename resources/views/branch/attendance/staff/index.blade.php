@extends('layouts.branch')
@section('title', __('Teacher & staff attendance'))

@section('content')
<x-page-header :title="__('Teacher & staff attendance')" :breadcrumbs="[__('Attendance') => route('branch.attendance.students.index'), __('Teachers & staff') => null]">
    <x-slot:actions>
        @can('staff-attendance.mark')
            <a href="{{ route('branch.attendance.scan') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-qrcode me-1"></i>{{ __('Scan station') }}</a>
        @endcan
        <a href="{{ route('branch.attendance.reports.staff') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-table-cells me-1"></i>{{ __('Monthly sheet') }}</a>
    </x-slot:actions>
</x-page-header>

<form method="GET" class="panel panel-body mb-4 d-flex flex-wrap align-items-end gap-2">
    <div><label class="form-label small mb-1" for="date">{{ __('Date') }}</label>
        <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1" for="type">{{ __('Type') }}</label>
        <select id="type" name="type" class="form-select form-select-sm">
            <option value="">{{ __('Teachers & staff') }}</option>
            <option value="teacher" @selected($type === 'teacher')>{{ __('Teachers') }}</option>
            <option value="staff" @selected($type === 'staff')>{{ __('Other staff') }}</option>
        </select></div>
    <div><label class="form-label small mb-1" for="department_id">{{ __('Department') }}</label>
        <select id="department_id" name="department_id" class="form-select form-select-sm">
            <option value="">{{ __('All departments') }}</option>
            @foreach($departments as $id => $name)<option value="{{ $id }}" @selected((int) request('department_id') === $id)>{{ $name }}</option>@endforeach
        </select></div>
    <button class="btn btn-brand btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Show') }}</button>
    <div class="ms-auto small text-body-secondary">{{ __('Office hours') }}: <strong>{{ $settings['staff_start'] }}–{{ $settings['staff_end'] }}</strong> · {{ __('grace') }} {{ bn_digits($settings['staff_grace_minutes']) }} {{ __('min') }}</div>
</form>

<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Employees')" :value="bn_digits($rows->count())" icon="fa-solid fa-users" bg="#d4c5f2" color="#6d28d9" :sub="__('active on this date')" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Present')" :value="bn_digits(($counts['present'] ?? 0) + ($counts['late'] ?? 0) + ($counts['half_day'] ?? 0))" icon="fa-solid fa-user-check" bg="#bfead6" color="#15803d" :sub="__('late').': '.bn_digits($counts['late'] ?? 0)" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Absent')" :value="bn_digits($counts['absent'] ?? 0)" icon="fa-solid fa-user-xmark" bg="#fbd0d0" color="#b91c1c" :sub="__('not marked').': '.bn_digits($rows->whereNull('record')->count())" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('On leave')" :value="bn_digits(($counts['leave'] ?? 0) + ($counts['excused'] ?? 0))" icon="fa-solid fa-plane-departure" bg="#bfd7fd" color="#1d4ed8" :sub="__('approved leave')" /></div>
</div>

@if($markableReason)
    <div class="alert alert-warning"><i class="fa-solid fa-circle-info me-1"></i> {{ $markableReason }}</div>
@endif

@if($rows->isEmpty())
    <div class="card"><x-empty-state icon="person-badge" :title="__('No active employees')" /></div>
@else
<form method="POST" action="{{ route('branch.attendance.staff.store', request()->only('type', 'department_id')) }}" class="card">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    @error('entries')<div class="alert alert-danger m-3">{{ $message }}</div>@enderror
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Status') }}</th><th style="width:110px">{{ __('In') }}</th><th style="width:110px">{{ __('Out') }}</th><th>{{ __('Late / early / OT') }}</th><th style="width:180px">{{ __('Remarks') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                @php
                    $e = $row['employee'];
                    $rec = $row['record'];
                    $leaveLocked = $rec && $rec->leave_application_id && $rec->status === App\Enums\AttendanceStatus::Leave;
                    $rowEditable = $canMark && (! $rec || $date->isToday()) && ! $leaveLocked;
                    $current = old('entries.'.$e->id.'.status', $rec?->status->value ?? ($row['leave'] ? 'leave' : 'present'));
                @endphp
                <tr @class(['table-info' => $row['leave'] !== null])>
                    <td><div class="fw-medium">{{ $e->name }}</div><div class="small text-body-secondary"><code>{{ $e->employee_code }}</code> · {{ $e->designation?->name ?? ($e->employee_type === 'teacher' ? __('Teacher') : __('Staff')) }}</div>
                        @if($row['leave'])<div class="small text-primary"><i class="fa-solid fa-plane-departure"></i> {{ $row['leave']->leaveType?->name }}</div>@endif</td>
                    <td>
                        @if($rowEditable)
                            <select name="entries[{{ $e->id }}][status]" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                                @foreach($statuses as $st)<option value="{{ $st->value }}" @selected($current === $st->value)>{{ $st->label() }}</option>@endforeach
                            </select>
                        @elseif($rec)
                            <span class="badge text-bg-{{ $rec->status->tone() }}">{{ $rec->status->label() }}</span>
                        @else
                            <span class="text-body-secondary">—</span>
                        @endif
                    </td>
                    <td>@if($rowEditable)<input type="time" name="entries[{{ $e->id }}][check_in]" value="{{ old('entries.'.$e->id.'.check_in', $rec?->check_in_at?->format('H:i')) }}" class="form-control form-control-sm" aria-label="{{ __('In') }}">@else{{ $rec?->check_in_at?->format('H:i') ?? '—' }}@endif</td>
                    <td>@if($rowEditable)<input type="time" name="entries[{{ $e->id }}][check_out]" value="{{ old('entries.'.$e->id.'.check_out', $rec?->check_out_at?->format('H:i')) }}" class="form-control form-control-sm" aria-label="{{ __('Out') }}">@else{{ $rec?->check_out_at?->format('H:i') ?? '—' }}@endif</td>
                    <td class="small">
                        @if($rec)
                            @if($rec->late_minutes)<span class="text-warning">{{ __('late') }} {{ bn_digits($rec->late_minutes) }}′</span>@endif
                            @if($rec->early_leave_minutes)<span class="text-danger ms-1">{{ __('early') }} {{ bn_digits($rec->early_leave_minutes) }}′</span>@endif
                            @if($rec->overtime_minutes)<span class="text-success ms-1">OT {{ bn_digits($rec->overtime_minutes) }}′</span>@endif
                            @if($rec->method !== 'manual')<span class="badge text-bg-light border ms-1">{{ $rec->method }}</span>@endif
                        @endif
                    </td>
                    <td>@if($rowEditable)<input name="entries[{{ $e->id }}][remarks]" value="{{ old('entries.'.$e->id.'.remarks', $rec?->remarks) }}" class="form-control form-control-sm" maxlength="255" aria-label="{{ __('Remarks') }}">@else<span class="small">{{ $rec?->remarks }}</span>@endif</td>
                    <td class="text-end">
                        @if($rec && ! $rowEditable && $canCorrect && ! $leaveLocked)
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#correctionModal"
                                data-record="{{ $rec->id }}" data-type="staff" data-name="{{ $e->name }}" data-status="{{ $rec->status->value }}" title="{{ __('Request correction') }}"><i class="bi bi-pencil"></i></button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($canMark)
        <div class="card-footer bg-transparent d-flex gap-2 align-items-center">
            <button class="btn btn-brand"><i class="fa-solid fa-floppy-disk me-1"></i>{{ __('Save register') }}</button>
            <span class="small text-body-secondary">{{ __('Late, early leave and overtime are calculated from the times and the branch rules.') }}</span>
        </div>
    @endif
</form>
@endif

@include('branch.attendance.partials.correction-modal', ['correctionRoute' => route('branch.attendance.corrections.store'), 'withTimes' => true])
@endsection
