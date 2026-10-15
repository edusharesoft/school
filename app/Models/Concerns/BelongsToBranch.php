<?php

namespace App\Models\Concerns;

use App\Exceptions\CrossTenantViolation;
use App\Exceptions\TenantContextMissing;
use App\Models\Branch;
use App\Models\Institution;
use App\Models\Scopes\BranchScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to every tenant-owned model (table has institution_id + branch_id).
 *
 * - Adds BranchScope (reads are isolated, route-model binding returns 404 across branches).
 * - On create: fills institution_id/branch_id from the context; rejects foreign ids.
 * - On update: branch_id / institution_id are immutable (a record never "moves" silently).
 *
 * @mixin Model
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model) {
            $context = app(TenantContext::class);

            if ($context->isBranch()) {
                if ($model->branch_id !== null && (int) $model->branch_id !== $context->branchId()) {
                    throw new CrossTenantViolation;
                }
                $model->branch_id = $context->branchId();
                $model->institution_id = $context->institutionId();

                return;
            }

            if ($context->isCentral()) {
                if ($model->branch_id === null) {
                    throw TenantContextMissing::forModel($model::class);
                }
                $branch = Branch::query()->find($model->branch_id);
                if (! $branch) {
                    throw new CrossTenantViolation('Unknown branch.');
                }
                if ($context->institutionId() !== null && $branch->institution_id !== $context->institutionId()) {
                    throw new CrossTenantViolation;
                }
                $model->institution_id = $branch->institution_id;

                return;
            }

            throw TenantContextMissing::forModel($model::class);
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('branch_id') || $model->isDirty('institution_id')) {
                throw new CrossTenantViolation('The owning branch of a record cannot be changed. Use a transfer workflow.');
            }
        });
    }

    public function initializeBelongsToBranch(): void
    {
        // Tenant keys are never mass assignable.
        $this->guarded = array_values(array_unique(array_merge($this->guarded, ['institution_id', 'branch_id'])));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** Explicitly bypass the tenant scope (central reports only — caller must authorise). */
    public static function withoutTenancy(): Builder
    {
        return static::withoutGlobalScope(BranchScope::class);
    }
}
