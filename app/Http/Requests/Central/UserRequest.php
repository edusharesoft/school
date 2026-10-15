<?php

namespace App\Http\Requests\Central;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'username' => $this->filled('username') ? trim((string) $this->input('username')) : null,
            'phone' => $this->filled('phone') ? preg_replace('/[\s-]/', '', (string) $this->input('phone')) : null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'name_bn' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($id)],
            'username' => ['nullable', 'alpha_dash:ascii', 'min:3', 'max:60', Rule::unique('users', 'username')->ignore($id)],
            'phone' => ['nullable', 'regex:/^\+?\d{6,15}$/', Rule::unique('users', 'phone')->ignore($id)],
            'user_type' => ['required', Rule::in(array_diff(array_column(UserType::cases(), 'value'), ['platform']))],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'locale' => ['nullable', Rule::in(config('app.supported_locales'))],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'branches' => ['nullable', 'array'],
            'branches.*' => ['integer', Rule::exists('branches', 'id')],
            'default_branch_id' => ['nullable', 'integer', 'in_array:branches.*'],
            'institution_id' => ['nullable', 'integer', Rule::exists('institutions', 'id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $actor = $this->user();
            $institutionId = $this->targetInstitutionId();

            // Only the Super Admin may grant protected roles.
            foreach ((array) $this->input('roles', []) as $role) {
                if (in_array($role, config('school.protected_roles'), true) && ! $actor->isSuperAdmin()) {
                    $validator->errors()->add('roles', __('You cannot assign the :role role.', ['role' => $role]));
                }
            }

            // Branches must belong to the target institution (prevents cross-tenant assignment by ID tampering).
            $branchIds = array_map('intval', (array) $this->input('branches', []));
            if ($branchIds) {
                $valid = Branch::query()->whereIn('id', $branchIds)->where('institution_id', $institutionId)->count();
                if ($valid !== count(array_unique($branchIds))) {
                    $validator->errors()->add('branches', __('One or more branches do not belong to this institution.'));
                }
            }

            // Branch-scoped roles need at least one branch.
            $branchScoped = Role::query()->whereIn('name', (array) $this->input('roles', []))->where('scope', 'branch')->exists();
            if ($branchScoped && ! $branchIds) {
                $validator->errors()->add('branches', __('Select at least one branch for branch-level roles.'));
            }
        }];
    }

    public function targetInstitutionId(): ?int
    {
        $actor = $this->user();
        if ($target = $this->route('user')) {
            return $target->institution_id;
        }

        return $actor->isPlatformOwner() ? ($this->integer('institution_id') ?: null) : $actor->institution_id;
    }
}
