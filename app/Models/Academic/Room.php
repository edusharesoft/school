<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const TYPES = ['classroom', 'lab', 'hall', 'library', 'other'];

    protected $fillable = ['name', 'code', 'building', 'type', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }
}
