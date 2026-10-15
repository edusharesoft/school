<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\Subject;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('subject');

        return $model instanceof Subject ? $this->user()->can('update', $model) : $this->user()->can('create', Subject::class);
    }

    public function rules(): array
    {
        $id = $this->route('subject')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'code' => ['required', 'alpha_dash:ascii', 'max:20', TenantRule::unique('subjects', 'code', $id)],
            'type' => ['required', Rule::in(Subject::TYPES)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
