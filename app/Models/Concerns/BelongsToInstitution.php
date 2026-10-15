<?php

namespace App\Models\Concerns;

use App\Exceptions\CrossTenantViolation;
use App\Exceptions\TenantContextMissing;
use App\Models\Institution;
use App\Models\Scopes\InstitutionScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For institution-wide records shared by all branches of one institution.
 *
 * @mixin Model
 */
trait BelongsToInstitution
{
    public static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope(new InstitutionScope);

        static::creating(function (Model $model) {
            $context = app(TenantContext::class);
            $institutionId = $context->institutionId();

            if ($institutionId !== null) {
                if ($model->institution_id !== null && (int) $model->institution_id !== $institutionId) {
                    throw new CrossTenantViolation('This record belongs to another institution.');
                }
                $model->institution_id = $institutionId;

                return;
            }

            // Platform owner in central mode must name the institution explicitly.
            if ($context->isCentral() && $model->institution_id !== null) {
                return;
            }

            throw TenantContextMissing::forModel($model::class);
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('institution_id')) {
                throw new CrossTenantViolation('The owning institution of a record cannot be changed.');
            }
        });
    }

    public function initializeBelongsToInstitution(): void
    {
        $this->guarded = array_values(array_unique(array_merge($this->guarded, ['institution_id'])));
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
