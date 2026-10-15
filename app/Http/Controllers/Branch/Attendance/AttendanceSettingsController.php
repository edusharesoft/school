<?php

namespace App\Http\Controllers\Branch\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendancePunch;
use App\Services\Attendance\AttendanceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Branch attendance rules (times, grace, holidays, alerts) and registered attendance devices. */
class AttendanceSettingsController extends Controller
{
    public function __construct(protected AttendanceSettings $settings) {}

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('branch.attendance.settings', [
            'values' => $this->settings->all(),
            'devices' => AttendanceDevice::query()->orderBy('name')->get(),
            'punches' => AttendancePunch::query()->with('device:id,name')->latest('id')->limit(20)->get(),
            'newKey' => session('device_key'),
            'endpoint' => url('/api/v1/attendance/punches'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.update');

        $time = ['required', 'date_format:H:i'];
        $data = $request->validate([
            'student_start' => $time,
            'student_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'staff_start' => $time,
            'staff_end' => [...$time, 'after:staff_start'],
            'staff_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'early_leave_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'overtime_min_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'half_day_minutes' => ['required', 'integer', 'min:30', 'max:600'],
            'backdate_days' => ['required', 'integer', 'min:0', 'max:31'],
            'absence_alert_days' => ['required', 'integer', 'min:2', 'max:30'],
            'weekly_holidays' => ['nullable', 'array', 'max:3'],
            'weekly_holidays.*' => ['integer', 'between:1,7'],
        ]);

        $data['weekly_holidays'] = array_values(array_map('intval', $data['weekly_holidays'] ?? []));
        foreach (['student_grace_minutes', 'staff_grace_minutes', 'early_leave_grace_minutes', 'overtime_min_minutes', 'half_day_minutes', 'backdate_days', 'absence_alert_days'] as $key) {
            $data[$key] = (int) $data[$key];
        }

        $this->settings->save($data);

        return back()->with('success', __('Attendance settings saved.'));
    }

    public function storeDevice(Request $request): RedirectResponse
    {
        $this->authorize('settings.update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(AttendanceDevice::TYPES)],
        ]);

        $device = new AttendanceDevice($data + ['is_active' => true]);
        $key = $device->issueKey();
        $device->save();

        // The plain key is shown once; only its hash is stored.
        return back()->with('device_key', ['name' => $device->name, 'key' => $key])->with('success', __('Device registered. Copy the key now — it will not be shown again.'));
    }

    public function rotateDevice(AttendanceDevice $device): RedirectResponse
    {
        $this->authorize('settings.update');

        $key = $device->issueKey();
        $device->save();

        return back()->with('device_key', ['name' => $device->name, 'key' => $key])->with('success', __('A new key was issued. The old key stops working immediately.'));
    }

    public function toggleDevice(AttendanceDevice $device): RedirectResponse
    {
        $this->authorize('settings.update');
        $device->update(['is_active' => ! $device->is_active]);

        return back()->with('success', $device->is_active ? __('Device enabled.') : __('Device disabled.'));
    }
}
