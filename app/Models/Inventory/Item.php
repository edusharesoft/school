<?php

namespace App\Models\Inventory;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock item. quantity_on_hand / stock_value change only through InventoryService (every change leaves a StockTransaction).
 */
class Item extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const TYPES = ['consumable', 'durable'];

    protected $table = 'inventory_items';

    protected $fillable = ['name', 'name_bn', 'inventory_category_id', 'item_type', 'unit', 'reorder_level', 'location', 'description', 'is_active'];

    protected $guarded = ['code', 'quantity_on_hand', 'stock_value'];

    protected array $auditExclude = ['quantity_on_hand', 'stock_value'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'reorder_level' => 'float', 'quantity_on_hand' => 'float', 'stock_value' => 'float',
            'inventory_category_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'inventory_category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'inventory_item_id');
    }

    /** Average cost per unit of what is on the shelves now (0 when nothing is left). */
    public function averageCost(): float
    {
        return $this->quantity_on_hand > 0 ? round($this->stock_value / $this->quantity_on_hand, 4) : 0.0;
    }

    public function isLow(): bool
    {
        return $this->reorder_level > 0 && $this->quantity_on_hand <= $this->reorder_level;
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }

    public function scopeLow(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('reorder_level', '>', 0)->whereColumn('quantity_on_hand', '<=', 'reorder_level');
    }
}
