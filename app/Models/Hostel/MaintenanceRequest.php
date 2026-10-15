<?php

namespace App\Models\Hostel;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceRequest extends Model
{
    use Auditable, BelongsToBranch;

    public const PRIORITIES = ['low', 'normal', 'high'];

    public const STATUSES = ['open', 'in_progress', 'done', 'cancelled'];

    protected $table = 'hostel_maintenance_requests';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'cost' => 'decimal:2', 'resolved_on' => 'date'];
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'hostel_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'hostel_room_id');
    }
}
