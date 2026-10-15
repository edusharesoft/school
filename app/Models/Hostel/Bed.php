<?php

namespace App\Models\Hostel;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bed extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'hostel_beds';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'is_active' => 'boolean'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'hostel_room_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'hostel_bed_id');
    }
}
