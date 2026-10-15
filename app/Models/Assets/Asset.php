<?php

namespace App\Models\Assets;

use App\Models\Academic\Room;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Finance\Vendor;
use App\Models\Inventory\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A fixed asset. Cost, accumulated depreciation and status change only through the asset services (every change that touches money
 * leaves a journal entry), so the register and the "Fixed assets" / "Accumulated depreciation" accounts never drift apart.
 */
class Asset extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['in_stock', 'in_use', 'disposed', 'voided'];

    public const CONDITIONS = ['new', 'good', 'fair', 'poor', 'damaged'];

    public const ACQUISITIONS = ['purchase', 'stock', 'donation', 'opening'];

    protected $fillable = ['name', 'name_bn', 'asset_category_id', 'serial_no', 'brand', 'model', 'vendor_id', 'bill_no', 'condition', 'room_id', 'location',
        'warranty_expires_on', 'safety_notes', 'description'];

    protected array $auditExclude = ['accumulated_depreciation', 'months_depreciated', 'depreciated_through'];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date', 'in_service_on' => 'date', 'warranty_expires_on' => 'date', 'last_verified_on' => 'date', 'disposed_on' => 'date',
            'cost' => 'float', 'salvage_value' => 'float', 'accumulated_depreciation' => 'float', 'declining_rate' => 'float',
            'useful_life_months' => 'integer', 'months_depreciated' => 'integer', 'is_under_repair' => 'boolean', 'is_missing' => 'boolean',
            'asset_category_id' => 'integer', 'vendor_id' => 'integer', 'room_id' => 'integer', 'inventory_item_id' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'asset_category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'inventory_item_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class)->orderByDesc('allocated_on')->orderByDesc('id');
    }

    public function currentAllocation(): HasOne
    {
        return $this->hasOne(Allocation::class)->whereNull('returned_on')->latestOfMany();
    }

    public function plans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class)->orderBy('next_due_on');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)->orderByDesc('reported_on')->orderByDesc('id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(Depreciation::class)->orderByDesc('period')->orderByDesc('id');
    }

    public function disposal(): HasOne
    {
        return $this->hasOne(Disposal::class)->where('status', 'posted')->latestOfMany();
    }

    public function bookValue(): float
    {
        return round($this->cost - $this->accumulated_depreciation, 2);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }

    /** Still owned (not disposed of, not cancelled). */
    public function isLive(): bool
    {
        return in_array($this->status, ['in_stock', 'in_use'], true);
    }

    public function isDepreciable(): bool
    {
        return match ($this->depreciation_method) {
            'straight_line' => $this->useful_life_months > 0,
            'declining_balance' => $this->declining_rate > 0,
            default => false,
        };
    }

    public function warrantyDaysLeft(): ?int
    {
        return $this->warranty_expires_on ? (int) now()->startOfDay()->diffInDays($this->warranty_expires_on->startOfDay(), false) : null;
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', ['in_stock', 'in_use']);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $query : $query->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('name_bn', 'like', "%$term%")
            ->orWhere('code', 'like', "%$term%")->orWhere('serial_no', 'like', "%$term%")->orWhere('brand', 'like', "%$term%")->orWhere('model', 'like', "%$term%"));
    }
}
