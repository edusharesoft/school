<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use BelongsToBranch;

    protected $table = 'purchase_order_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'float', 'received_quantity' => 'float', 'inventory_item_id' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'inventory_item_id')->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function pending(): float
    {
        return max(0.0, round($this->quantity - $this->received_quantity, 3));
    }
}
