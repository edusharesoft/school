<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Chart-of-accounts entry. Debit-normal: asset, expense. Credit-normal: liability, equity, income. */
class Account extends Model
{
    use Auditable, BelongsToBranch;

    public const TYPES = ['asset', 'liability', 'equity', 'income', 'expense'];

    public const KINDS = ['cash', 'bank', 'mobile'];

    protected $fillable = ['parent_id', 'code', 'name', 'name_bn', 'type', 'is_group', 'kind', 'bank_name', 'account_number', 'is_active', 'description'];

    /** System keys are assigned only by ChartOfAccounts. */
    protected $guarded = ['system_key'];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'is_active' => 'boolean', 'parent_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_group', false)->where('is_active', true);
    }

    /** Cash, bank and mobile-banking accounts that receive or pay money. */
    public function scopeMoney(Builder $query): Builder
    {
        return $query->postable()->whereNotNull('kind')->orderBy('code');
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }
}
