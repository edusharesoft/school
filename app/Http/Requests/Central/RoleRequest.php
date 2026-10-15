<?php

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role ? $this->user()->can('update', $role) : $this->user()->can('create', Role::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge(['name' => str((string) $this->input('name'))->slug()->toString()]);
        }
    }

    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            // System role names are immutable.
            'name' => [$role?->is_system ? 'prohibited' : 'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id)],
            'label' => ['required', 'string', 'max:191'],
            'label_bn' => ['nullable', 'string', 'max:191'],
            'scope' => [$role?->is_system ? 'prohibited' : 'required', Rule::in(['central', 'branch'])],
            'portal' => [$role?->is_system ? 'prohibited' : 'required', Rule::in(['admin', 'teacher', 'student', 'parent'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }
}
