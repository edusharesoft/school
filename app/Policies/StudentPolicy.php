<?php

namespace App\Policies;

use App\Models\Students\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        return $user->checkPermissionTo('students.view') && $this->inBranch($student);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->checkPermissionTo('students.update') && $this->inBranch($student);
    }

    /** Status changes (withdraw / suspend / graduate) are an update, never a delete. */
    public function changeStatus(User $user, Student $student): bool
    {
        return $this->update($user, $student);
    }

    public function viewHealth(User $user, Student $student): bool
    {
        return $user->checkPermissionTo('students.view-health') && $this->inBranch($student);
    }

    public function import(User $user): bool
    {
        return $user->checkPermissionTo('students.import');
    }

    public function export(User $user): bool
    {
        return $user->checkPermissionTo('students.export');
    }

    public function promote(User $user): bool
    {
        return $user->checkPermissionTo('students.promote');
    }

    protected function inBranch(Student $student): bool
    {
        return tenant()->isBranch() && $student->branch_id === tenant()->branchId();
    }
}
