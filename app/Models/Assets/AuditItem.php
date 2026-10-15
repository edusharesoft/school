<?php

namespace App\Models\Assets;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditItem extends Model
{
    use BelongsToBranch;

    public const RESULTS = ['found', 'missing', 'damaged'];

    protected $table = 'asset_audit_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'asset_id' => 'integer', 'asset_audit_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class, 'asset_audit_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
