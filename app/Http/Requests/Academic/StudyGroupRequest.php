<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\StudyGroup;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudyGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('group');

        return $model instanceof StudyGroup ? $this->user()->can('update', $model) : $this->user()->can('create', StudyGroup::class);
    }

    public function rules(): array
    {
        $id = $this->route('group')?->id;

        return [
            'name' => ['required', 'string', 'max:60'],
            'name_bn' => ['nullable', 'string', 'max:60'],
            'code' => ['required', 'alpha_dash:ascii', 'max:20', TenantRule::unique('study_groups', 'code', $id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
