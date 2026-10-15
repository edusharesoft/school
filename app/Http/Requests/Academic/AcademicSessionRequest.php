<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\AcademicSession;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('session');

        return $model instanceof AcademicSession ? $this->user()->can('update', $model) : $this->user()->can('create', AcademicSession::class);
    }

    public function rules(): array
    {
        $id = $this->route('session')?->id;

        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('institution_id', tenant()->institutionId())],
            'name' => ['required', 'string', 'max:50', TenantRule::unique('academic_sessions', 'name', $id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ];
    }
}
