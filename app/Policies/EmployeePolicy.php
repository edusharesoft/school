<?php

namespace App\Policies;

use App\Models\HR\Employee;
use App\Models\User;

/** Employee records. Salary / NID are a separate ability (employees.view-salary). */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('employees.view');
    }

    /** HR users see everyone in the branch; an employee may always see their own record. */
    public function view(User $user, Employee $employee): bool
    {
        if (! $this->inBranch($employee)) {
            return false;
        }

        return $user->checkPermissionTo('employees.view') || $employee->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->checkPermissionTo('employees.update') && $this->inBranch($employee);
    }

    /** Resign / terminate / retire / suspend / reinstate — never a delete. */
    public function changeStatus(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee) && $employee->user_id !== $user->id;
    }

    public function viewConfidential(User $user, Employee $employee): bool
    {
        return $user->checkPermissionTo('employees.view-salary') && $this->inBranch($employee);
    }

    public function createAccount(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee) && $user->checkPermissionTo('users.create');
    }

    protected function inBranch(Employee $employee): bool
    {
        return tenant()->isBranch() && $employee->branch_id === tenant()->branchId();
    }
}
