<?php

namespace App\Models\Scopes;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Row-level tenant isolation for every model using BelongsToBranch.
 *
 *  branch mode  → WHERE branch_id = :current
 *  central mode → WHERE institution_id = :institution   (no filter for the platform owner)
 *  no context   → WHERE 1 = 0   (fail-closed: jobs/commands without context see nothing)
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        $table = $model->getTable();

        if ($context->isBranch()) {
            $builder->where($table.'.branch_id', $context->branchId());

            return;
        }

        if ($context->isCentral()) {
            if ($context->institutionId() !== null) {
                $builder->where($table.'.institution_id', $context->institutionId());
            }

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
