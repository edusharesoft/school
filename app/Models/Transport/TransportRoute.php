<?php

namespace App\Models\Transport;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportRoute extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    protected $table = 'transport_routes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'monthly_fee' => 'decimal:2', 'is_active' => 'boolean', 'vehicle_id' => 'integer', 'driver_id' => 'integer', 'helper_id' => 'integer'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'helper_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class, 'transport_route_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'transport_route_id');
    }
}
