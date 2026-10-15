<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeAllocation extends Model
{
    use BelongsToBranch;

    protected $fillable = ['fee_payment_id', 'fee_invoice_id', 'amount', 'from_credit', 'journal_entry_id'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'from_credit' => 'boolean', 'fee_payment_id' => 'integer', 'fee_invoice_id' => 'integer', 'journal_entry_id' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    /** Journal entry of an advance-credit application. */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }
}
