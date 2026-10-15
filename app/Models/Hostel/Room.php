<?php

namespace App\Models\Hostel;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const TYPES = ['single', 'double', 'shared', 'dormitory'];

    protected $table = 'hostel_rooms';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'monthly_fee' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'hostel_id');
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class, 'hostel_room_id');
    }
}
