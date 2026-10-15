<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records created / updated / deleted / restored events in audit_logs.
 * Models may define `protected array $auditExclude = [...]` to skip noisy columns.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(AuditLogger::class)->log('created', $model, [], $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            app(AuditLogger::class)->log('updated', $model, $old, $changes);
        });

        static::deleted(function (Model $model) {
            app(AuditLogger::class)->log('deleted', $model, $model->auditableAttributes($model->getAttributes()));
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                app(AuditLogger::class)->log('restored', $model);
            });
        }
    }

    public function auditableAttributes(array $attributes): array
    {
        $exclude = array_merge(['updated_at', 'created_at'], property_exists($this, 'auditExclude') ? $this->auditExclude : []);

        return array_diff_key($attributes, array_flip($exclude));
    }
}
