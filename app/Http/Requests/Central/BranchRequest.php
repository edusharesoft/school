<?php

namespace App\Http\Requests\Central;

use App\Models\Branch;
use App\Models\Institution;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            ? $this->user()->can('update', $branch)
            : $this->user()->can('create', Branch::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => strtolower(trim((string) $this->input('subdomain'))),
            'code' => strtoupper(trim((string) $this->input('code'))),
        ]);
    }

    public function rules(): array
    {
        /** @var Branch|null $branch */
        $branch = $this->route('branch');
        $institutionId = $branch?->institution_id ?? $this->targetInstitutionId();

        return [
            'institution_id' => [$branch ? 'prohibited' : 'nullable', 'integer', Rule::exists('institutions', 'id')],
            'name' => ['required', 'string', 'max:191'],
            'name_bn' => ['nullable', 'string', 'max:191'],
            'code' => ['required', 'alpha_dash:ascii', 'max:30',
                Rule::unique('branches', 'code')->where('institution_id', $institutionId)->ignore($branch?->id)],
            'subdomain' => ['required', 'string', 'min:2', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(config('tenancy.reserved_subdomains')),
                Rule::unique('branches', 'subdomain')->ignore($branch?->id)],
            'custom_domain' => [$branch ? 'prohibited' : 'nullable', 'string', 'max:191',
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
                Rule::unique('domains', 'domain')],
            'type' => ['required', Rule::in(['school', 'college', 'school_college'])],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'principal_name' => ['nullable', 'string', 'max:191'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'established_on' => ['nullable', 'date', 'before_or_equal:today'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /** Institution the new branch belongs to: the user's own, or (platform owner) the chosen one. */
    public function targetInstitutionId(): ?int
    {
        $user = $this->user();
        if (! $user->isPlatformOwner()) {
            return $user->institution_id;
        }

        return $this->integer('institution_id') ?: app(TenantContext::class)->institutionId()
            ?: Institution::query()->orderBy('id')->value('id');
    }
}
