<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $year = $this->route('academic_year');

        return $year instanceof AcademicYear ? $this->user()->can('update', $year) : $this->user()->can('create', AcademicYear::class);
    }

    public function rules(): array
    {
        $year = $this->route('academic_year');
        $institutionId = $year?->institution_id ?? $this->targetInstitutionId();

        return [
            'institution_id' => [$year ? 'prohibited' : 'nullable', 'integer', Rule::exists('institutions', 'id')],
            'name' => ['required', 'string', 'max:30', Rule::unique('academic_years', 'name')->where('institution_id', $institutionId)->ignore($year?->id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }

    public function targetInstitutionId(): ?int
    {
        return $this->user()->isPlatformOwner() ? ($this->integer('institution_id') ?: null) : $this->user()->institution_id;
    }
}
