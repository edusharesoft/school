<?php

namespace App\Models\Inventory;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockAudit extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'stock_audits';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'completed_at' => 'datetime', 'inventory_category_id' => 'integer', 'started_by' => 'integer',
            'completed_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAuditItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'inventory_category_id');
    }

    public function adjustment(): HasOne
    {
        return $this->hasOne(StockAdjustment::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
