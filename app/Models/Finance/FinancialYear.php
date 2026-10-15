<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['name', 'starts_on', 'ends_on'];

    protected $guarded = ['is_closed', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_closed' => 'boolean', 'closed_at' => 'datetime', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }
}
