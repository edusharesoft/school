<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;

class InstitutionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('institutions.view');
    }

    public function view(User $user, Institution $institution): bool
    {
        return $user->checkPermissionTo('institutions.view') && $this->owns($user, $institution);
    }

    public function create(User $user): bool
    {
        return $user->isPlatformOwner() && $user->checkPermissionTo('institutions.create');
    }

    public function update(User $user, Institution $institution): bool
    {
        return $user->checkPermissionTo('institutions.update') && $this->owns($user, $institution);
    }

    protected function owns(User $user, Institution $institution): bool
    {
        return $user->isPlatformOwner() || $user->institution_id === $institution->id;
    }
}
