<?php

namespace App\Http\Requests\HR;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class LeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leaves.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'name_bn' => ['nullable', 'string', 'max:80'],
            'code' => ['required', 'alpha_dash', 'max:20', TenantRule::unique('leave_types', 'code', $this->route('leave_type')?->id)],
            'days_per_year' => ['required', 'integer', 'min:0', 'max:366'],
            'is_paid' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
