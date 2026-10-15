<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Self-service institution registration (only when enabled in Settings). */
class RegisterRequest extends FormRequest
{
    /** Closed registration is a 404 before any validation runs (the endpoint "does not exist"). */
    public function authorize(): bool
    {
        abort_unless(\App\Support\AuthTheme::registrationOpen(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'mobile' => preg_replace('/[\s-]/', '', (string) $this->input('mobile')),
            'subdomain' => trim(strtolower((string) $this->input('subdomain')), '-'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'mobile' => ['required', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/', Rule::unique('users', 'phone')],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'institution_name' => ['required', 'string', 'max:191'],
            'subdomain' => ['required', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(config('tenancy.reserved_subdomains')), Rule::unique('branches', 'subdomain')],
            'address' => ['required', 'string', 'max:1000'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => __('Enter a valid Bangladeshi mobile number (01XXXXXXXXX).'),
            'subdomain.unique' => __('This address is already taken. Please choose another.'),
            'subdomain.not_in' => __('This address is reserved. Please choose another.'),
            'terms.accepted' => __('Please accept the terms and privacy policy.'),
        ];
    }
}
