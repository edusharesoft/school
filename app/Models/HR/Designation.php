<?php

namespace App\Models\HR;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const CATEGORIES = ['teaching', 'non_teaching'];

    protected $fillable = ['name', 'name_bn', 'code', 'category', 'rank', 'is_active'];

    protected function casts(): array
    {
        return ['rank' => 'integer', 'is_active' => 'boolean', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('rank')->orderBy('name');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
