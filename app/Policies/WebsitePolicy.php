<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Pages and posts of the public website — governed by the "website.*" permissions. */
class WebsitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('website.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->checkPermissionTo('website.view') && $this->owns($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('website.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->checkPermissionTo('website.update') && $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->checkPermissionTo('website.delete') && $this->owns($user, $model);
    }

    /**
     * Institution administrators only touch their own institution's content, and a branch host only edits the
     * content of its own branch site (never the institution-wide site, nor another branch's).
     */
    protected function owns(User $user, Model $model): bool
    {
        $branchId = app(\App\Tenancy\TenantContext::class)->branchId();
        if ($branchId !== null ? (int) $model->branch_id !== $branchId : $model->branch_id !== null) {
            return false;
        }

        return $user->isPlatformOwner() || (int) $user->institution_id === (int) $model->institution_id;
    }
}
