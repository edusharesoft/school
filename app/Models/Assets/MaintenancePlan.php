<?php

namespace App\Models\Assets;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Finance\Vendor;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A recurring task for an asset: service, calibration, inspection, safety check. */
class MaintenancePlan extends Model
{
    use Auditable, BelongsToBranch;

    public const KINDS = ['preventive', 'calibration', 'inspection', 'safety'];

    protected $table = 'asset_maintenance_plans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_on' => 'date', 'last_done_on' => 'date', 'next_due_on' => 'date', 'last_notified_on' => 'date', 'is_active' => 'boolean',
            'interval_months' => 'integer', 'asset_id' => 'integer', 'employee_id' => 'integer', 'vendor_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function daysUntilDue(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->next_due_on->startOfDay(), false);
    }

    public function isOverdue(): bool
    {
        return $this->is_active && $this->daysUntilDue() < 0;
    }

    /** Active plans of assets that are still owned. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('asset', fn ($a) => $a->whereIn('status', ['in_stock', 'in_use']));
    }
}
