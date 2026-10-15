<?php

namespace App\Http\Requests\Students;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('promote', \App\Models\Students\Student::class);
    }

    public function rules(): array
    {
        return [
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:500'],
            'enrollment_ids.*' => ['integer', TenantRule::exists('student_enrollments')],
            'target_session_id' => ['required', 'integer', TenantRule::exists('academic_sessions')],
            'target_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)],
            'target_section_id' => ['nullable', 'integer', TenantRule::exists('sections', 'id', true)],
            'outcome' => ['required', Rule::in(['promoted', 'repeated'])],
        ];
    }
}
