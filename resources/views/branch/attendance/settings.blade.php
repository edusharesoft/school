@extends('layouts.branch')
@section('title', __('Attendance rules & devices'))

@php($canEdit = auth()->user()->can('settings.update'))
@php($days = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')])

@section('content')
<x-page-header :title="__('Attendance rules & devices')" :breadcrumbs="[__('Attendance') => route('branch.attendance.students.index'), __('Rules & devices') => null]" />

@if($newKey)
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1"><i class="fa-solid fa-key me-1"></i> {{ __('Device key for :name', ['name' => $newKey['name']]) }}</div>
        <code class="user-select-all fs-6">{{ $newKey['key'] }}</code>
        <div class="small mt-1">{{ __('Copy it into the device / middleware now. It is stored only as a hash and cannot be shown again.') }}</div>
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-7">
        <form method="POST" action="{{ route('branch.attendance.settings.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header bg-transparent"><h2 class="h6 mb-0"><i class="fa-solid fa-sliders me-1 text-primary"></i> {{ __('Rules for :branch', ['branch' => $currentBranch->name]) }}</h2></div>
            <fieldset class="card-body row g-3" @disabled(! $canEdit)>
                <div class="col-12 small fw-semibold text-uppercase text-body-secondary">{{ __('Students') }}</div>
                <x-form.input name="student_start" type="time" :label="__('Class start')" :value="$values['student_start']" required col="col-md-4" />
                <x-form.input name="student_grace_minutes" type="number" min="0" max="120" :label="__('Grace (minutes)')" :value="$values['student_grace_minutes']" required col="col-md-4"
                    :help="__('Scans after start + grace are marked Late.')" />

                <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('Teachers & staff') }}</div>
                <x-form.input name="staff_start" type="time" :label="__('Office start')" :value="$values['staff_start']" required col="col-md-3" />
                <x-form.input name="staff_end" type="time" :label="__('Office end')" :value="$values['staff_end']" required col="col-md-3" />
                <x-form.input name="staff_grace_minutes" type="number" min="0" max="120" :label="__('Late grace (min)')" :value="$values['staff_grace_minutes']" required col="col-md-3" />
                <x-form.input name="early_leave_grace_minutes" type="number" min="0" max="120" :label="__('Early-leave grace (min)')" :value="$values['early_leave_grace_minutes']" required col="col-md-3" />
                <x-form.input name="overtime_min_minutes" type="number" min="0" max="240" :label="__('Overtime counted from (min)')" :value="$values['overtime_min_minutes']" required col="col-md-4" />
                <x-form.input name="half_day_minutes" type="number" min="30" max="600" :label="__('Half-day if worked less than (min)')" :value="$values['half_day_minutes']" required col="col-md-4" />

                <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('Registers & alerts') }}</div>
                <x-form.input name="backdate_days" type="number" min="0" max="31" :label="__('Back-dating window (days)')" :value="$values['backdate_days']" required col="col-md-4"
                    :help="__('A missed register may still be taken this many days later.')" />
                <x-form.input name="absence_alert_days" type="number" min="2" max="30" :label="__('Absence alert after (days)')" :value="$values['absence_alert_days']" required col="col-md-4" />
                <div class="col-12">
                    <label class="form-label d-block">{{ __('Weekly holidays') }}</label>
                    @foreach($days as $n => $name)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="weekly_holidays[]" value="{{ $n }}" id="wh{{ $n }}" @checked(in_array($n, array_map('intval', (array) old('weekly_holidays', $values['weekly_holidays'])), true))>
                            <label class="form-check-label" for="wh{{ $n }}">{{ $name }}</label>
                        </div>
                    @endforeach
                    @error('weekly_holidays')<div class="small text-danger">{{ $message }}</div>@enderror
                </div>
            </fieldset>
            @if($canEdit)
                <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Save rules') }}</button></div>
            @endif
        </form>
    </div>

    <div class="col-xl-5">
        <div class="dp purple mb-4">
            <div class="dp-head"><h2><i class="fa-solid fa-fingerprint me-1"></i> {{ __('Attendance devices') }}</h2></div>
            <div class="dp-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($devices as $d)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <div class="me-auto"><div class="fw-medium">{{ $d->name }} <span class="badge text-bg-light border">{{ $d->type }}</span></div>
                                <div class="small text-body-secondary">{{ __('Key') }} …{{ $d->api_key_hint }} · {{ __('Last seen') }}: {{ $d->last_seen_at?->diffForHumans() ?? __('never') }}</div></div>
                            <x-status-badge :status="$d->is_active ? 'active' : 'inactive'" />
                            @if($canEdit)
                                <form method="POST" action="{{ route('branch.attendance.devices.toggle', $d) }}">@csrf
                                    <button class="btn btn-sm btn-outline-secondary" title="{{ $d->is_active ? __('Disable') : __('Enable') }}"><i class="bi bi-power"></i></button></form>
                                <form method="POST" action="{{ route('branch.attendance.devices.rotate', $d) }}" data-confirm="{{ __('Issue a new key? The old key stops working immediately.') }}">@csrf
                                    <button class="btn btn-sm btn-outline-warning" title="{{ __('New key') }}"><i class="bi bi-arrow-repeat"></i></button></form>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary small">{{ __('No devices registered. Scanning from the browser works without a device.') }}</li>
                    @endforelse
                </ul>
                @if($canEdit)
                    <form method="POST" action="{{ route('branch.attendance.devices.store') }}" class="row g-2 p-3 border-top">
                        @csrf
                        <div class="col-md-5"><input name="name" class="form-control form-control-sm" placeholder="{{ __('Name, e.g. Main gate') }}" required maxlength="100" aria-label="{{ __('Name') }}"></div>
                        <div class="col-md-4"><select name="type" class="form-select form-select-sm" aria-label="{{ __('Type') }}">
                            @foreach(App\Models\Attendance\AttendanceDevice::TYPES as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                        </select></div>
                        <div class="col-md-3"><button class="btn btn-sm btn-brand w-100">{{ __('Register') }}</button></div>
                        <div class="col-12"><input name="serial_no" class="form-control form-control-sm" placeholder="{{ __('Serial number (optional)') }}" maxlength="100" aria-label="{{ __('Serial number') }}"></div>
                    </form>
                @endif
            </div>
        </div>

        <div class="dp blue mb-4">
            <div class="dp-head"><h2><i class="fa-solid fa-plug me-1"></i> {{ __('Device integration') }}</h2></div>
            <div class="dp-body small">
                <p class="mb-2">{{ __('Biometric / RFID terminals (or the vendor middleware in front of them) push punches to:') }}</p>
                <code class="d-block user-select-all mb-2">POST {{ $endpoint }}</code>
                <pre class="bg-body-tertiary p-2 rounded mb-2" style="font-size:11px">X-Device-Key: dev_…
Content-Type: application/json

{"punches":[{"code":"{{ $currentBranch->code }}-E0001","time":"{{ now()->format('Y-m-d H:i:s') }}"}]}</pre>
                <p class="mb-0 text-body-secondary">{{ __('Enrol people on the device with their Employee ID or Student ID. Times are Bangladesh local time. Re-sent punches are ignored, so devices may safely retry.') }}</p>
            </div>
        </div>

        <div class="dp green">
            <div class="dp-head"><h2><i class="fa-solid fa-list me-1"></i> {{ __('Latest device punches') }}</h2></div>
            <div class="dp-body p-0">
                <ul class="list-group list-group-flush small">
                    @forelse($punches as $p)
                        <li class="list-group-item d-flex gap-2"><span class="me-auto"><code>{{ $p->person_code }}</code> · {{ $p->punched_at->format('d M H:i') }} · {{ $p->device?->name }}</span>
                            <span class="badge text-bg-{{ ['applied' => 'success', 'duplicate' => 'secondary', 'unknown' => 'danger'][$p->result] ?? 'warning' }}">{{ $p->result }}</span></li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No punches received yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
