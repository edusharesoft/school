<?php

namespace App\Services\HR;

use App\Enums\UserType;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmploymentHistory;
use App\Models\Department;
use App\Models\User;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Employee lifecycle: joining (record + optional login account + history), profile changes that are
 * recorded as career events, and separation (resign / terminate / retire) which deactivates the login.
 */
class EmployeeService
{
    public function __construct(
        protected TenantContext $tenant,
        protected SequenceService $sequences,
    ) {}

    /**
     * @param  array  $data     Employee attributes (fillable)
     * @param  array|null  $account  ['email','password','role'] to create a login account, or null
     */
    public function create(array $data, ?array $account, ?User $actor): Employee
    {
        return DB::transaction(function () use ($data, $account, $actor) {
            $employee = new Employee(Arr::except($data, ['employee_code', 'user_id', 'status']));
            $employee->employee_code = $this->nextCode();
            $employee->status = 'active';
            $employee->save();

            $this->history($employee, 'joined', $employee->joining_date->toDateString(), null,
                $employee->designation?->name, __('Joined as :type', ['type' => __(ucfirst($employee->employee_type))]), $actor);

            if ($account) {
                $this->createAccount($employee, $account, $actor);
            }

            return $employee->fresh(['designation', 'department', 'user']);
        });
    }

    /** Branch code + running number, e.g. BR01-E0012. */
    public function nextCode(): string
    {
        $number = $this->sequences->next('employee_code');

        return sprintf('%s-E%04d', strtoupper($this->tenant->branch()->code), $number);
    }

    /** Update the profile; designation / department / type / salary changes are written to the history. */
    public function update(Employee $employee, array $data, User $actor, bool $canSeeSalary): Employee
    {
        if (! $canSeeSalary) {
            unset($data['basic_salary'], $data['nid_number']); // cannot overwrite what you cannot see
        }

        return DB::transaction(function () use ($employee, $data, $actor) {
            $before = [
                'designation_id' => $employee->designation_id,
                'department_id' => $employee->department_id,
                'employee_type' => $employee->employee_type,
                'basic_salary' => $employee->basic_salary,
            ];

            $employee->fill(Arr::except($data, ['employee_code', 'user_id', 'status']))->save();
            $today = now()->toDateString();

            if ($before['designation_id'] !== $employee->designation_id) {
                $this->history($employee, 'designation_changed', $today,
                    Designation::query()->find($before['designation_id'])?->name, $employee->designation()->first()?->name, null, $actor);
            }
            if ($before['department_id'] !== $employee->department_id) {
                $this->history($employee, 'department_changed', $today,
                    Department::query()->find($before['department_id'])?->name, $employee->department()->first()?->name, null, $actor);
            }
            if ($before['employee_type'] !== $employee->employee_type) {
                $this->history($employee, 'type_changed', $today, $before['employee_type'], $employee->employee_type, null, $actor);
                $employee->user?->update(['user_type' => $employee->employee_type === 'teacher' ? UserType::Teacher : UserType::Staff]);
            }
            if (array_key_exists('basic_salary', $data) && (string) $before['basic_salary'] !== (string) $employee->basic_salary) {
                // Amounts are not copied into the history (confidential); the event and date are.
                $this->history($employee, 'salary_revised', $today, null, null, __('Basic salary revised'), $actor);
            }

            return $employee;
        });
    }

    /** resigned | terminated | retired | suspended | active (reinstate). */
    public function changeStatus(Employee $employee, string $status, string $date, ?string $reason, User $actor): Employee
    {
        abort_unless(in_array($status, Employee::STATUSES, true), 422);

        if ($employee->status === $status) {
            throw ValidationException::withMessages(['status' => __('The employee already has this status.')]);
        }

        return DB::transaction(function () use ($employee, $status, $date, $reason, $actor) {
            $employee->forceFill([
                'status' => $status,
                'status_changed_on' => $date,
                'separation_reason' => in_array($status, Employee::SEPARATED, true) ? $reason : null,
            ])->save();

            $this->history($employee, $status === 'active' ? 'reinstated' : $status, $date, null, null, $reason, $actor);

            if ($employee->user) {
                // Separated or suspended employees lose their login; reinstated ones get it back.
                $employee->user->update(['status' => $status === 'active' ? 'active' : 'inactive']);
                if ($status !== 'active') {
                    $employee->user->tokens()->delete();
                }
            }

            return $employee;
        });
    }

    /**
     * Create a portal/back-office login for the employee (branch-scoped role, no privilege escalation).
     *
     * @param  array{email:string,password:string,role:string}  $account
     */
    public function createAccount(Employee $employee, array $account, User $actor): User
    {
        if ($employee->user_id) {
            throw ValidationException::withMessages(['account_email' => __('This employee already has a login account.')]);
        }

        $role = Role::query()->where('name', $account['role'])->where('guard_name', 'web')->first();
        if (! $role || ! $this->assignableRoles($actor)->contains('name', $role->name)) {
            throw ValidationException::withMessages(['account_role' => __('You cannot assign the :role role.', ['role' => $account['role']])]);
        }

        $branch = $this->tenant->branch();

        $user = new User([
            'name' => $employee->name,
            'name_bn' => $employee->name_bn,
            'email' => $account['email'],
            'phone' => User::query()->where('phone', $employee->phone)->exists() ? null : $employee->phone,
            'user_type' => $employee->employee_type === 'teacher' ? UserType::Teacher : UserType::Staff,
            'status' => 'active',
            'password' => $account['password'],
        ]);
        $user->institution_id = $branch->institution_id;
        $user->default_branch_id = $branch->id;
        $user->save();

        $user->branches()->syncWithoutDetaching([$branch->id => ['is_default' => true, 'granted_by' => $actor->id]]);
        $user->assignRole($role);

        $employee->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    /**
     * Roles the actor may give to an employee account: branch-scoped, not student/parent/protected,
     * and only roles whose permissions the actor already holds (no privilege escalation).
     *
     * @return Collection<int,Role>
     */
    public function assignableRoles(User $actor): Collection
    {
        $roles = Role::query()->with('permissions:id,name')
            ->where('guard_name', 'web')
            ->where(fn ($q) => $q->where('scope', 'branch')->orWhereNull('scope'))
            ->whereNotIn('name', array_merge(['student', 'parent'], config('school.protected_roles')))
            ->orderBy('name')->get();

        if ($actor->isSuperAdmin()) {
            return $roles;
        }

        $held = $actor->getAllPermissions()->pluck('name')->flip();

        return $roles->filter(fn (Role $role) => $role->permissions->every(fn ($p) => $held->has($p->name)))->values();
    }

    public function history(Employee $employee, string $event, string $date, ?string $from, ?string $to, ?string $note, ?User $actor): EmploymentHistory
    {
        return $employee->histories()->create([
            'event' => $event,
            'effective_on' => Carbon::parse($date)->toDateString(),
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note,
            'recorded_by' => $actor?->id,
        ]);
    }
}
