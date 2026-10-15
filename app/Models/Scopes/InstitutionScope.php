<?php

namespace App\Models\Scopes;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Isolation for institution-wide records (e.g. academic years):
 *  branch mode  → WHERE institution_id = branch's institution
 *  central mode → WHERE institution_id = :institution (none for platform owner)
 *  no context   → WHERE 1 = 0 (fail-closed)
 */
class InstitutionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        $column = $model->getTable().'.institution_id';

        if ($context->isBranch() || ($context->isCentral() && $context->institutionId() !== null)) {
            $builder->where($column, $context->institutionId());

            return;
        }

        if ($context->isCentral()) {
            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
