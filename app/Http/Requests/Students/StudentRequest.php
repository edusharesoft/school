<?php

namespace App\Http\Requests\Students;

use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admission (create) and profile edit (update). On create it also validates guardians[] and the
 * first enrollment; on update only the student profile (enrollment changes go through promotion).
 */
class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student ? $this->user()->can('update', $student) : $this->user()->can('create', Student::class);
    }

    protected function prepareForValidation(): void
    {
        $guardians = collect($this->input('guardians', []))
            ->filter(fn ($g) => is_array($g) && (filled($g['name'] ?? null) || filled($g['phone'] ?? null)))
            ->map(fn ($g) => array_merge($g, ['phone' => preg_replace('/[\s-]/', '', (string) ($g['phone'] ?? ''))]))
            ->values()->all();

        $this->merge([
            'guardians' => $guardians,
            'phone' => $this->filled('phone') ? preg_replace('/[\s-]/', '', (string) $this->input('phone')) : null,
        ]);
    }

    public function rules(): array
    {
        $creating = ! $this->route('student') instanceof Student;

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'name_bn' => ['nullable', 'string', 'max:191'],
            'gender' => ['required', Rule::in(Student::GENDERS)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'birth_registration_no' => ['nullable', 'regex:/^\d{17}$/',
                TenantRule::unique('students', 'birth_registration_no', $this->route('student')?->id)],
            'blood_group' => ['nullable', Rule::in(Student::BLOOD_GROUPS)],
            'religion' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'regex:/^\+?\d{6,15}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'present_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'previous_school' => ['nullable', 'string', 'max:191'],
            'admission_date' => [$creating ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'regex:/^\+?\d{6,15}$/'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024', 'dimensions:max_width=3000,max_height=3000'],
        ];

        if ($creating) {
            $rules += [
                'guardians' => ['required', 'array', 'min:1', 'max:3'],
                'guardians.*.name' => ['required', 'string', 'max:191'],
                'guardians.*.name_bn' => ['nullable', 'string', 'max:191'],
                'guardians.*.phone' => ['required', 'regex:/^\+?\d{6,15}$/'],
                'guardians.*.relation' => ['required', Rule::in(Guardian::RELATIONS)],
                'guardians.*.occupation' => ['nullable', 'string', 'max:100'],
                'guardians.*.email' => ['nullable', 'email', 'max:191'],
                'guardians.*.is_primary' => ['nullable', 'boolean'],
                'academic_session_id' => ['required', 'integer', TenantRule::exists('academic_sessions')],
                'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)],
                'section_id' => ['required', 'integer', TenantRule::exists('sections', 'id', true)],
                'study_group_id' => ['nullable', 'integer', TenantRule::exists('study_groups')],
                'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
            ];
        }

        return $rules;
    }

    /** Student profile attributes only. */
    public function studentData(): array
    {
        $data = collect($this->validated())->only([
            'name', 'name_bn', 'gender', 'date_of_birth', 'birth_registration_no', 'blood_group', 'religion', 'nationality',
            'phone', 'email', 'present_address', 'permanent_address', 'previous_school', 'admission_date',
            'emergency_contact_name', 'emergency_contact_phone', 'medical_notes',
        ])->all();

        $data['nationality'] = ($data['nationality'] ?? null) ?: 'Bangladeshi';

        return $data;
    }

    public function enrollmentData(): array
    {
        return collect($this->validated())->only(['academic_session_id', 'school_class_id', 'section_id', 'study_group_id', 'roll_no'])->all();
    }
}
