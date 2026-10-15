<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->input('type') === 'staff'
            ? $this->user()->can('staff-attendance.correct')
            : $this->user()->can('student-attendance.correct');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['student', 'staff'])],
            'record_id' => ['required', 'integer'],
            'requested_status' => ['required', Rule::in(AttendanceStatus::values())],
            'requested_check_in' => ['nullable', 'date_format:H:i'],
            'requested_check_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
