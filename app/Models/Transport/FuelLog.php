<?php

namespace App\Models\Transport;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelLog extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'transport_fuel_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'date' => 'date', 'litres' => 'decimal:2', 'total_cost' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'transport_vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'transport_driver_id');
    }
}
