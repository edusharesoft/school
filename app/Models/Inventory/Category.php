<?php

namespace App\Models\Inventory;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Finance\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use Auditable, BelongsToBranch;

    public const KINDS = ['general', 'laboratory'];

    protected $table = 'inventory_categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'branch_id' => 'integer', 'institution_id' => 'integer', 'expense_account_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'inventory_category_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }
}
