<?php

namespace App\Http\Requests\HR;

use App\Models\HR\LeaveApplication;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class LeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LeaveApplication::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_half_day' => $this->boolean('is_half_day')]);
        if ($this->boolean('is_half_day')) {
            $this->merge(['to_date' => $this->input('from_date')]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', TenantRule::exists('employees', 'id', true)],
            'leave_type_id' => ['required', 'integer', TenantRule::exists('leave_types', 'id', true)],
            'from_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:-90 days'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date', 'before_or_equal:+365 days'],
            'is_half_day' => ['boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
