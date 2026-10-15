<?php

namespace App\Policies;

use App\Models\Students\Guardian;
use App\Models\User;

class GuardianPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('guardians.view');
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $user->checkPermissionTo('guardians.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('guardians.create');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->checkPermissionTo('guardians.update');
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->checkPermissionTo('guardians.delete');
    }
}
