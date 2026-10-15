<?php

namespace App\Models\Transport;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stop extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'transport_stops';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'monthly_fee' => 'decimal:2', 'sequence' => 'integer'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function fee(): float
    {
        return (float) ($this->monthly_fee ?? $this->route?->monthly_fee ?? 0);
    }
}
