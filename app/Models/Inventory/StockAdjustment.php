<?php

namespace App\Models\Inventory;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use Auditable, BelongsToBranch;

    public const REASONS = ['opening', 'damage', 'loss', 'expired', 'found', 'recount', 'other'];

    protected $table = 'stock_adjustments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'net_value' => 'float', 'stock_audit_id' => 'integer', 'created_by' => 'integer', 'approved_by' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'stock_audit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
