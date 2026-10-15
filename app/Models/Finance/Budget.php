<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['financial_year_id', 'account_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'financial_year_id' => 'integer', 'account_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'financial_year_id');
    }
}
