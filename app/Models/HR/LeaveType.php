<?php

namespace App\Models\HR;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    protected $fillable = ['name', 'name_bn', 'code', 'days_per_year', 'is_paid', 'is_active'];

    protected function casts(): array
    {
        return ['days_per_year' => 'integer', 'is_paid' => 'boolean', 'is_active' => 'boolean', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isUnlimited(): bool
    {
        return $this->days_per_year === 0;
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
