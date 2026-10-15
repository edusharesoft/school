<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\SchoolClass;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('class');

        return $model instanceof SchoolClass ? $this->user()->can('update', $model) : $this->user()->can('create', SchoolClass::class);
    }

    public function rules(): array
    {
        $id = $this->route('class')?->id;

        return [
            'name' => ['required', 'string', 'max:60'],
            'name_bn' => ['nullable', 'string', 'max:60'],
            'code' => ['required', 'alpha_dash:ascii', 'max:20', TenantRule::unique('school_classes', 'code', $id)],
            'level' => ['required', Rule::in(SchoolClass::LEVELS)],
            'numeric_order' => ['required', 'integer', 'min:0', 'max:100'],
            'has_groups' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
