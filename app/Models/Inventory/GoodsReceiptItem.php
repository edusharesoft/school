<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use BelongsToBranch;

    protected $table = 'goods_receipt_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'float', 'amount' => 'float', 'inventory_item_id' => 'integer',
            'purchase_order_item_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'inventory_item_id')->withTrashed();
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }
}
