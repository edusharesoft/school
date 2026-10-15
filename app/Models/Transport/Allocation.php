<?php

namespace App\Models\Transport;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Allocation extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'transport_allocations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'monthly_fee' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'student_id' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Students\Student::class, 'student_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'transport_stop_id');
    }
}
