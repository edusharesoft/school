<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A money receipt. Never edited: a mistaken receipt is reversed (FeePaymentService::reverse). */
class FeePayment extends Model
{
    use Auditable, BelongsToBranch;

    public const METHODS = ['cash', 'bank', 'cheque', 'bkash', 'nagad', 'rocket', 'card', 'online'];

    protected $fillable = ['receipt_no', 'student_id', 'date', 'amount', 'unallocated', 'method', 'account_id', 'reference', 'notes'];

    protected $guarded = ['status', 'reverse_reason', 'reversed_at', 'reversed_by', 'received_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'float', 'unallocated' => 'float', 'reversed_at' => 'datetime', 'student_id' => 'integer', 'account_id' => 'integer',
            'received_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeeAllocation::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}
