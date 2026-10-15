<?php

namespace App\Http\Requests\Admissions;

use App\Models\Admissions\AdmissionCampaign;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdmissionCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('campaign') ? 'admissions.update' : 'admissions.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_online' => $this->boolean('is_online'),
            'has_test' => $this->boolean('has_test'),
            'fee_before_admission' => $this->boolean('fee_before_admission'),
            'required_documents' => array_values(array_filter((array) $this->input('required_documents', []))),
        ]);
    }

    public function rules(): array
    {
        $modes = ['hidden', 'optional', 'required'];

        return [
            'title' => ['required', 'string', 'max:191'],
            'academic_session_id' => ['required', 'integer', TenantRule::exists('academic_sessions')],
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'seats' => ['required', 'integer', 'min:1', 'max:5000'],
            'is_online' => ['boolean'],
            'has_test' => ['boolean'],
            'test_at' => ['nullable', 'required_if:has_test,true', 'date'],
            'test_venue' => ['nullable', 'string', 'max:191'],
            'test_total_marks' => ['nullable', 'required_if:has_test,true', 'numeric', 'min:1', 'max:1000'],
            'test_pass_marks' => ['nullable', 'numeric', 'min:0'],
            'min_age' => ['nullable', 'integer', 'min:2', 'max:40'],
            'max_age' => ['nullable', 'integer', 'min:2', 'max:60'],
            'admission_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'fee_before_admission' => ['boolean'],
            'required_documents' => ['array'],
            'required_documents.*' => [Rule::in(AdmissionCampaign::DOCUMENT_TYPES)],
            'form_settings' => ['nullable', 'array'],
            'form_settings.*' => [Rule::in($modes)],
            'status' => ['required', Rule::in(AdmissionCampaign::STATUSES)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('min_age') && $this->filled('max_age') && (int) $this->input('max_age') < (int) $this->input('min_age')) {
                $validator->errors()->add('max_age', __('The maximum age must be at least the minimum age.'));
            }
            if ($this->boolean('has_test') && $this->filled('test_pass_marks') && $this->filled('test_total_marks')
                && (float) $this->input('test_pass_marks') > (float) $this->input('test_total_marks')) {
                $validator->errors()->add('test_pass_marks', __('Pass marks cannot exceed the total marks.'));
            }
        });
    }

    public function campaignData(): array
    {
        $data = $this->validated();
        $data['form_settings'] = array_intersect_key($data['form_settings'] ?? [], array_flip(AdmissionCampaign::OPTIONAL_FIELDS));
        if (! $data['has_test']) {
            $data['test_at'] = $data['test_venue'] = $data['test_total_marks'] = $data['test_pass_marks'] = null;
        }

        return $data;
    }
}
