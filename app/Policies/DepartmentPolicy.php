<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

/**
 * Branch ownership is already guaranteed by BranchScope (a foreign department is a 404
 * before the policy runs); the explicit branch check below is defence in depth.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->checkPermissionTo('departments.view') && $this->inCurrentBranch($department);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->checkPermissionTo('departments.update') && $this->inCurrentBranch($department);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->checkPermissionTo('departments.delete') && $this->inCurrentBranch($department);
    }

    protected function inCurrentBranch(Department $department): bool
    {
        return tenant()->isBranch() && (int) $department->branch_id === tenant()->branchId();
    }
}
