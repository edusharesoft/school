<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockIssueItem extends Model
{
    use BelongsToBranch;

    protected $table = 'stock_issue_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'amount' => 'float', 'inventory_item_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'inventory_item_id')->withTrashed();
    }
}
