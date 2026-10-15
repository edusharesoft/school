<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeHead extends Model
{
    use Auditable, BelongsToBranch;

    public const CATEGORIES = ['admission', 'tuition', 'session', 'exam', 'lab', 'library', 'transport', 'hostel', 'development', 'fine', 'other'];

    protected $fillable = ['name', 'name_bn', 'code', 'category', 'income_account_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer', 'income_account_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
