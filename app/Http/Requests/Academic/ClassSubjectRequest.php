<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\ClassSubject;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClassSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('class'));
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', TenantRule::exists('subjects', 'id', true)],
            'study_group_id' => ['nullable', 'integer', TenantRule::exists('study_groups')],
            'category' => ['required', Rule::in(ClassSubject::CATEGORIES)],
            'full_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['required', 'integer', 'min:0', 'lte:full_marks'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $current = $this->route('classSubject');
            $exists = ClassSubject::query()
                ->where('school_class_id', $this->route('class')->id)
                ->where('subject_id', $this->integer('subject_id'))
                ->when($this->filled('study_group_id'), fn ($q) => $q->where('study_group_id', $this->integer('study_group_id')),
                    fn ($q) => $q->whereNull('study_group_id'))
                ->when($current, fn ($q) => $q->whereKeyNot($current->id))
                ->exists();

            if ($exists) {
                $validator->errors()->add('subject_id', __('This subject is already assigned to the class for that group.'));
            }
        }];
    }
}
