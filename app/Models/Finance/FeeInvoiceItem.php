<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Line of an invoice (reached only through its invoice, which carries the tenant scope). */
class FeeInvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['fee_head_id', 'description', 'amount', 'discount', 'discount_note', 'is_late_fee'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'discount' => 'float', 'is_late_fee' => 'boolean', 'fee_head_id' => 'integer', 'fee_invoice_id' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }

    public function net(): float
    {
        return round($this->amount - $this->discount, 2);
    }
}
