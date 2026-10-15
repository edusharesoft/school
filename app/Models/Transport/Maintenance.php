<?php

namespace App\Models\Transport;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    use Auditable, BelongsToBranch;

    public const KINDS = ['service', 'repair', 'inspection', 'tyres', 'other'];

    protected $table = 'transport_maintenance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'date' => 'date', 'cost' => 'decimal:2', 'next_due_on' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'transport_vehicle_id');
    }
}
