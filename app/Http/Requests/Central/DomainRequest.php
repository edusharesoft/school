<?php

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageDomains', $this->route('branch'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['domain' => strtolower(trim((string) $this->input('domain')))]);
    }

    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:191',
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                Rule::notIn(config('tenancy.central_domains')),
                Rule::unique('domains', 'domain')],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
