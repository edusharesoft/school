<?php

namespace App\Http\Requests\HR;

use App\Models\HR\Designation;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employees.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'code' => ['required', 'alpha_dash', 'max:30', TenantRule::unique('designations', 'code', $this->route('designation')?->id)],
            'category' => ['required', Rule::in(Designation::CATEGORIES)],
            'rank' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }
}
