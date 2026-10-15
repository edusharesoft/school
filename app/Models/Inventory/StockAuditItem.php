<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditItem extends Model
{
    use BelongsToBranch;

    protected $table = 'stock_audit_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_quantity' => 'float', 'counted_quantity' => 'float', 'inventory_item_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'inventory_item_id')->withTrashed();
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'stock_audit_id');
    }

    /** Counted − book stock when the audit was opened; null until the item is counted. */
    public function variance(): ?float
    {
        return $this->counted_quantity === null ? null : round($this->counted_quantity - $this->expected_quantity, 3);
    }
}
