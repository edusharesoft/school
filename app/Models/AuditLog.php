<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/** Append-only. update() / delete() are blocked at model level. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'institution_id', 'branch_id', 'user_id', 'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'description', 'ip_address', 'user_agent', 'url',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Restrict to what the current tenant context may see. */
    public function scopeVisibleInContext(Builder $query): Builder
    {
        $context = app(TenantContext::class);

        if ($context->isBranch()) {
            return $query->where('branch_id', $context->branchId());
        }

        if ($context->isCentral()) {
            return $context->institutionId() ? $query->where('institution_id', $context->institutionId()) : $query;
        }

        return $query->whereRaw('1 = 0');
    }
}
