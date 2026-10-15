<?php

namespace App\Models\Assets;

use App\Models\Academic\Room;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Physical verification of the register ("asset audit"): a count sheet of live assets, optionally of one category or room. */
class Audit extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'asset_audits';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'completed_at' => 'datetime', 'asset_category_id' => 'integer', 'room_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AuditItem::class, 'asset_audit_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'asset_category_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
