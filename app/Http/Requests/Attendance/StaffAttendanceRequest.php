<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('staff-attendance.mark');
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1', 'max:1000'],
            'entries.*.status' => ['required', Rule::in(AttendanceStatus::values())],
            'entries.*.check_in' => ['nullable', 'date_format:H:i'],
            'entries.*.check_out' => ['nullable', 'date_format:H:i'],
            'entries.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
