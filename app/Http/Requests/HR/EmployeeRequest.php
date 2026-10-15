<?php

namespace App\Http\Requests\HR;

use App\Models\HR\Employee;
use App\Models\Students\Student;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Create (with optional login account) and update of an employee profile. */
class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee ? $this->user()->can('update', $employee) : $this->user()->can('create', Employee::class);
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => $v === null || $v === '' ? null : preg_replace('/[\s-]/', '', (string) $v);

        $this->merge([
            'phone' => $clean($this->input('phone')),
            'emergency_contact_phone' => $clean($this->input('emergency_contact_phone')),
            'nid_number' => $clean($this->input('nid_number')),
            'create_account' => $this->boolean('create_account'),
        ]);
    }

    public function rules(): array
    {
        $employee = $this->route('employee');
        $creating = ! $employee instanceof Employee;

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'name_bn' => ['nullable', 'string', 'max:191'],
            'employee_type' => ['required', Rule::in(Employee::TYPES)],
            'employment_type' => ['required', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'department_id' => ['nullable', 'integer', TenantRule::exists('departments', 'id', true)],
            'designation_id' => ['nullable', 'integer', TenantRule::exists('designations', 'id', true)],
            'gender' => ['required', Rule::in(Employee::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:-16 years', 'after:1940-01-01'],
            'phone' => ['required', 'regex:/^\+?\d{6,15}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'nid_number' => ['nullable', 'regex:/^(\d{10}|\d{13}|\d{17})$/'],
            'blood_group' => ['nullable', Rule::in(Student::BLOOD_GROUPS)],
            'religion' => ['nullable', 'string', 'max:30'],
            'present_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'joining_date' => ['required', 'date', 'before_or_equal:+60 days'],
            'qualification' => ['nullable', 'string', 'max:2000'],
            'experience_years' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'basic_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'regex:/^\+?\d{6,15}$/'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024', 'dimensions:max_width=3000,max_height=3000'],
        ];

        if ($creating) {
            $rules += [
                'create_account' => ['boolean'],
                'account_email' => ['exclude_unless:create_account,true', 'required', 'email', 'max:191', Rule::unique('users', 'email')],
                'account_password' => ['exclude_unless:create_account,true', 'required', Password::min(8)->letters()->numbers()],
                'account_role' => ['exclude_unless:create_account,true', 'required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            ];
        }

        return $rules;
    }

    public function employeeData(): array
    {
        $data = collect($this->validated())->except(['photo', 'create_account', 'account_email', 'account_password', 'account_role'])->all();
        $data['experience_years'] = $data['experience_years'] ?? 0;

        return $data;
    }

    /** @return array{email:string,password:string,role:string}|null */
    public function accountData(): ?array
    {
        if (! $this->boolean('create_account') || $this->route('employee') instanceof Employee) {
            return null;
        }

        return ['email' => $this->validated('account_email'), 'password' => $this->validated('account_password'), 'role' => $this->validated('account_role')];
    }
}
