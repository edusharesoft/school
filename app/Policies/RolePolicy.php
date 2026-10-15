<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Roles are platform-wide, so only the Super Admin (via Gate::before) may create, change or
 * delete them. Everyone with roles.view can inspect them. (Per-institution custom roles: Part 7.)
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->checkPermissionTo('roles.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Role $role): bool
    {
        return false;
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }
}
