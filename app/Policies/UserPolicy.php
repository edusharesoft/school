<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo('users.view') && $this->manageable($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo('users.update') && $this->manageable($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->checkPermissionTo('users.delete') && $actor->isNot($target) && $this->manageable($actor, $target);
    }

    /** Same institution, and nobody but a Super Admin (Gate::before) may touch a Super Admin. */
    protected function manageable(User $actor, User $target): bool
    {
        if ($target->isSuperAdmin()) {
            return false;
        }

        return $actor->isPlatformOwner() || ($actor->institution_id !== null && $actor->institution_id === $target->institution_id);
    }
}
