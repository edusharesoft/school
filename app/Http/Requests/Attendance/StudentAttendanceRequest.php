<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Saving a class register. Section-level authorisation (class teacher / permission) is checked in the controller. */
class StudentAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('student-attendance.mark');
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', TenantRule::exists('sections', 'id', true)],
            'subject_id' => ['nullable', 'integer', TenantRule::exists('subjects', 'id', true)],
            'date' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1', 'max:300'],
            'entries.*.status' => ['required', Rule::in(AttendanceStatus::values())],
            'entries.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
