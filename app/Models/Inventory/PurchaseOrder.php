<?php

namespace App\Models\Inventory;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Finance\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Purchase order: draft → approved → partial → received (or cancelled). Status changes only through ProcurementService. */
class PurchaseOrder extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['draft', 'approved', 'partial', 'received', 'cancelled'];

    protected $table = 'purchase_orders';

    protected $fillable = ['vendor_id', 'order_date', 'expected_on', 'notes'];

    protected $guarded = ['po_no', 'status', 'total', 'inventory_requisition_id', 'created_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_on' => 'date', 'approved_at' => 'datetime', 'total' => 'float', 'vendor_id' => 'integer',
            'inventory_requisition_id' => 'integer', 'created_by' => 'integer', 'approved_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class, 'inventory_requisition_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, ['approved', 'partial'], true);
    }
}
