<?php

namespace App\Models\Assets;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Finance\Vendor;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A work order on an asset. open → in_progress → completed | cancelled. Cost is recorded for reporting only. */
class MaintenanceLog extends Model
{
    use Auditable, BelongsToBranch;

    public const KINDS = ['preventive', 'calibration', 'inspection', 'safety', 'repair', 'upgrade'];

    public const OPEN = ['open', 'in_progress'];

    protected $table = 'asset_maintenance_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reported_on' => 'date', 'started_on' => 'date', 'completed_on' => 'date', 'cost' => 'float', 'takes_offline' => 'boolean',
            'asset_id' => 'integer', 'asset_maintenance_plan_id' => 'integer', 'employee_id' => 'integer', 'vendor_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'asset_maintenance_plan_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
