<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use BelongsToBranch;

    public $timestamps = false;

    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'student_id', 'description', 'date'];

    /** Set only by the bank reconciliation screen. */
    protected $guarded = ['reconciled_on', 'reconciled_by'];

    protected function casts(): array
    {
        return ['debit' => 'float', 'credit' => 'float', 'date' => 'date', 'reconciled_on' => 'date', 'account_id' => 'integer', 'student_id' => 'integer', 'journal_entry_id' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
