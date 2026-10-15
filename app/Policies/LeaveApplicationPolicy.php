<?php

namespace App\Policies;

use App\Models\HR\LeaveApplication;
use App\Models\User;

/**
 * Leave applications. Users with leaves.approve (or employees.view) see the whole branch;
 * everyone else sees only their own applications (matched through employees.user_id).
 */
class LeaveApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('leaves.view');
    }

    public function viewAll(User $user): bool
    {
        return $user->checkPermissionTo('leaves.approve') || $user->checkPermissionTo('employees.view');
    }

    public function view(User $user, LeaveApplication $application): bool
    {
        if (! $this->inBranch($application)) {
            return false;
        }

        return $this->viewAll($user) || $application->employee?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('leaves.create');
    }

    /** Apply on behalf of another employee (HR desk). */
    public function createForOthers(User $user): bool
    {
        return $user->checkPermissionTo('leaves.create') && $user->checkPermissionTo('leaves.approve');
    }

    public function review(User $user, LeaveApplication $application): bool
    {
        return $user->checkPermissionTo('leaves.approve') && $this->inBranch($application)
            && $application->employee?->user_id !== $user->id;
    }

    public function cancel(User $user, LeaveApplication $application): bool
    {
        if (! $this->inBranch($application)) {
            return false;
        }
        if ($application->status === 'pending' && $application->employee?->user_id === $user->id) {
            return true;
        }

        return in_array($application->status, ['pending', 'approved'], true) && $this->review($user, $application);
    }

    protected function inBranch(LeaveApplication $application): bool
    {
        return tenant()->isBranch() && $application->branch_id === tenant()->branchId();
    }
}
