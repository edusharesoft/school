<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItem extends Model
{
    use BelongsToBranch;

    protected $table = 'inventory_requisition_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'estimated_unit_cost' => 'float', 'inventory_item_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'inventory_item_id')->withTrashed();
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class, 'inventory_requisition_id');
    }
}
