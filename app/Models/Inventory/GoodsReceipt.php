<?php

namespace App\Models\Inventory;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Finance\Account;
use App\Models\Finance\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Goods received note. Created and reversed only through ProcurementService. */
class GoodsReceipt extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'goods_receipts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'total' => 'float', 'paid_amount' => 'float', 'vendor_id' => 'integer', 'purchase_order_id' => 'integer',
            'paid_from_account_id' => 'integer', 'received_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function due(): float
    {
        return $this->status === 'posted' ? max(0.0, round($this->total - $this->paid_amount, 2)) : 0.0;
    }
}
