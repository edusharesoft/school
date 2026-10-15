<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One line of the stock card. Never edited or deleted — a mistake is corrected by a reversal. */
class StockTransaction extends Model
{
    use BelongsToBranch;

    protected $table = 'stock_transactions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'quantity' => 'float', 'amount' => 'float', 'balance_quantity' => 'float', 'balance_value' => 'float',
            'inventory_item_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'inventory_item_id')->withTrashed();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
