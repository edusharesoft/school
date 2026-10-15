<?php

namespace App\Http\Requests\Branch;

use App\Models\Department;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            ? $this->user()->can('update', $department)
            : $this->user()->can('create', Department::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    public function rules(): array
    {
        $branchId = app(TenantContext::class)->branchId();

        return [
            'name' => ['required', 'string', 'max:191'],
            'name_bn' => ['nullable', 'string', 'max:191'],
            'code' => ['required', 'alpha_dash:ascii', 'max:30',
                Rule::unique('departments', 'code')->where('branch_id', $branchId)->ignore($this->route('department')?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
