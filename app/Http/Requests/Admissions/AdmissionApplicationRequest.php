<?php

namespace App\Http\Requests\Admissions;

use App\Models\Admissions\AdmissionCampaign;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Application form — public (online, campaign from the route) and back-office (offline, campaign posted).
 * Optional fields and required documents come from the campaign's configuration.
 */
class AdmissionApplicationRequest extends FormRequest
{
    protected ?AdmissionCampaign $resolved = null;

    public function authorize(): bool
    {
        return $this->isOnline() || $this->user()?->can('admissions.create');
    }

    public function isOnline(): bool
    {
        return $this->route('campaign') instanceof AdmissionCampaign;
    }

    public function campaign(): ?AdmissionCampaign
    {
        return $this->resolved ??= $this->isOnline()
            ? $this->route('campaign')
            : AdmissionCampaign::query()->find($this->integer('admission_campaign_id'));
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => $v === null || $v === '' ? null : preg_replace('/[\s-]/', '', (string) $v);
        $this->merge([
            'phone' => $clean($this->input('phone')),
            'guardian_phone' => $clean($this->input('guardian_phone')),
            'emergency_contact_phone' => $clean($this->input('emergency_contact_phone')),
            'birth_registration_no' => $clean($this->input('birth_registration_no')),
        ]);
    }

    public function rules(): array
    {
        $campaign = $this->campaign();
        $mode = fn (string $field, array $rules) => ! $campaign || ! $campaign->shows($field)
            ? ['exclude']
            : array_merge([$campaign->requires($field) ? 'required' : 'nullable'], $rules);
        $phone = ['regex:/^(\+?88)?01[3-9]\d{8}$/'];

        $rules = [
            'website' => ['nullable', 'max:0'], // honeypot: real people never fill this hidden field
            'name' => ['required', 'string', 'max:191'],
            'name_bn' => $mode('name_bn', ['string', 'max:191']),
            'gender' => ['required', Rule::in(Student::GENDERS)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1990-01-01'],
            'birth_registration_no' => $mode('birth_registration_no', ['regex:/^\d{17}$/']),
            'religion' => $mode('religion', ['string', 'max:30']),
            'blood_group' => $mode('blood_group', [Rule::in(Student::BLOOD_GROUPS)]),
            'phone' => ['required', ...$phone],
            'email' => $mode('email', ['email', 'max:191']),
            'present_address' => ['required', 'string', 'max:1000'],
            'permanent_address' => $mode('permanent_address', ['string', 'max:1000']),
            'previous_school' => $mode('previous_school', ['string', 'max:191']),
            'previous_class' => $mode('previous_class', ['string', 'max:60']),
            'previous_result' => $mode('previous_result', ['string', 'max:60']),
            'father_name' => $mode('father_name', ['string', 'max:191']),
            'mother_name' => $mode('mother_name', ['string', 'max:191']),
            'guardian_name' => ['required', 'string', 'max:191'],
            'guardian_phone' => ['required', ...$phone],
            'guardian_relation' => ['required', Rule::in(Guardian::RELATIONS)],
            'guardian_occupation' => $mode('guardian_occupation', ['string', 'max:100']),
            'emergency_contact_phone' => $mode('emergency_contact_phone', $phone),
            'photo' => $mode('photo', ['image', 'mimes:jpg,jpeg,png,webp', 'max:1024', 'dimensions:max_width=3000,max_height=3000']),
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:5120'],
        ];

        foreach ($campaign?->required_documents ?? [] as $type) {
            $rules['documents.'.$type] = ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:5120'];
        }

        if (! $this->isOnline()) {
            $rules['admission_campaign_id'] = ['required', 'integer', TenantRule::exists('admission_campaigns', 'id', true)];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $out = [];
        foreach (AdmissionCampaign::DOCUMENT_TYPES as $type) {
            $out['documents.'.$type] = __(ucfirst(str_replace('_', ' ', $type)));
        }

        return $out;
    }

    public function applicationData(): array
    {
        return collect($this->validated())->except(['website', 'photo', 'documents', 'admission_campaign_id'])->all();
    }

    /** @return array<string,\Illuminate\Http\UploadedFile> */
    public function documentFiles(): array
    {
        return array_intersect_key((array) $this->file('documents', []), array_flip(AdmissionCampaign::DOCUMENT_TYPES));
    }
}
