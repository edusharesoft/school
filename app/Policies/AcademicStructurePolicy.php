<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One policy for all academic-structure models (years, sessions, terms, classes, sections,
 * groups, subjects, class-subject combinations, rooms) — they share the "academics.*" permissions.
 * Branch ownership is enforced by BranchScope/InstitutionScope before a model reaches the policy.
 */
class AcademicStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('academics.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->checkPermissionTo('academics.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('academics.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->checkPermissionTo('academics.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->checkPermissionTo('academics.delete');
    }
}
