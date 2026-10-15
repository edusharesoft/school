<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->checkPermissionTo('branches.view') && $this->sameInstitution($user, $branch);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->checkPermissionTo('branches.update') && $this->sameInstitution($user, $branch);
    }

    public function manageDomains(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    protected function sameInstitution(User $user, Branch $branch): bool
    {
        return $user->isPlatformOwner() || $user->institution_id === $branch->institution_id;
    }
}
