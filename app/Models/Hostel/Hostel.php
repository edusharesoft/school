<?php

namespace App\Models\Hostel;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hostel extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const TYPES = ['boys', 'girls', 'mixed'];

    protected $table = 'hostels';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'is_active' => 'boolean', 'warden_id' => 'integer'];
    }

    public function warden(): BelongsTo
    {
        return $this->belongsTo(\App\Models\HR\Employee::class, 'warden_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'hostel_id');
    }
}
