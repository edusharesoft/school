<?php

namespace App\Http\Requests\Central;

use App\Models\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $institution = $this->route('institution');

        return $institution instanceof Institution
            ? $this->user()->can('update', $institution)
            : $this->user()->can('create', Institution::class);
    }

    public function rules(): array
    {
        $id = $this->route('institution')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'name_bn' => ['nullable', 'string', 'max:191'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('institutions', 'slug')->ignore($id)],
            'code' => ['required', 'alpha_dash:ascii', 'max:30', Rule::unique('institutions', 'code')->ignore($id)],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:191'],
            'address' => ['nullable', 'string', 'max:1000'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ];
    }
}
