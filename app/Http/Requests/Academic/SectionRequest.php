<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\Section;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('section');

        return $model instanceof Section ? $this->user()->can('update', $model) : $this->user()->can('create', Section::class);
    }

    public function rules(): array
    {
        $id = $this->route('section')?->id;

        return [
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)],
            'name' => ['required', 'string', 'max:40', Rule::unique('sections', 'name')->where('school_class_id', $this->integer('school_class_id'))->ignore($id)],
            'shift' => ['nullable', Rule::in(['morning', 'day', 'evening'])],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'class_teacher_id' => ['nullable', 'integer', Rule::exists('branch_user', 'user_id')->where('branch_id', tenant()->branchId())],
            'room_id' => ['nullable', 'integer', TenantRule::exists('rooms', 'id', true)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
