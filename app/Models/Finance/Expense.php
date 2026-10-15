<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Expense voucher. Status and posting fields change only through ExpenseService. */
class Expense extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['pending', 'approved', 'paid', 'rejected', 'reversed'];

    protected $fillable = ['expense_no', 'date', 'account_id', 'vendor_id', 'description', 'amount', 'payment_mode', 'paid_from_account_id', 'reference', 'attachment_path'];

    protected $guarded = ['status', 'review_note', 'requested_by', 'approved_by', 'approved_at', 'paid_on'];

    protected function casts(): array
    {
        return ['date' => 'date', 'paid_on' => 'date', 'approved_at' => 'datetime', 'amount' => 'float', 'account_id' => 'integer', 'vendor_id' => 'integer',
            'paid_from_account_id' => 'integer', 'requested_by' => 'integer', 'approved_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function tone(): string
    {
        return match ($this->status) { 'paid' => 'success', 'approved' => 'primary', 'pending' => 'warning', 'rejected', 'reversed' => 'secondary', default => 'light' };
    }
}
