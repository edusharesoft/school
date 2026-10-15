<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A posted voucher. Never edited after posting; corrections are made with a reversal entry. */
class JournalEntry extends Model
{
    use BelongsToBranch;

    public const TYPES = ['invoice', 'receipt', 'payment', 'refund', 'journal', 'contra', 'reversal', 'expense', 'payroll'];

    protected $fillable = ['financial_year_id', 'entry_no', 'date', 'type', 'narration', 'source_type', 'source_id', 'amount', 'reversal_of_id', 'created_by'];

    protected $guarded = ['status', 'reversed_by_id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'float', 'financial_year_id' => 'integer', 'reversal_of_id' => 'integer', 'reversed_by_id' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}
