<?php

namespace App\Models\Library;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\HR\Employee;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Overdue / lost / damaged charge. Paid fines are posted to the accounts (Dr cash/bank, Cr Late fees & fines). */
class LibraryFine extends Model
{
    use Auditable, BelongsToBranch;

    public const KINDS = ['overdue', 'lost', 'damaged'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'member_id' => 'integer', 'library_transaction_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LibraryTransaction::class, 'library_transaction_id');
    }

    public function memberModel(): Student|Employee|null
    {
        if (! $this->relationLoaded('memberModel')) {
            $this->setRelation('memberModel', \App\Services\Library\LibraryMembers::find($this->member_type, (int) $this->member_id));
        }

        return $this->getRelation('memberModel');
    }

    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }
}
