<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money paid back to a student out of advance credit. */
class FeeRefund extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['refund_no', 'student_id', 'date', 'amount', 'method', 'account_id', 'reference', 'reason', 'sources'];

    protected $guarded = ['refunded_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'float', 'sources' => 'array', 'student_id' => 'integer', 'account_id' => 'integer', 'refunded_by' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
