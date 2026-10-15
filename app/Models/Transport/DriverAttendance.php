<?php

namespace App\Models\Transport;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverAttendance extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'transport_driver_attendance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'date' => 'date:Y-m-d'];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'transport_driver_id');
    }
}
