<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One slot of the bell schedule (a class period or a break). */
class Period extends Model
{
    use Auditable, BelongsToBranch;

    public const SHIFTS = ['morning', 'day', 'evening'];

    protected $fillable = ['name', 'shift', 'starts_at', 'ends_at', 'is_break', 'sort_order'];

    protected function casts(): array
    {
        return ['is_break' => 'boolean', 'sort_order' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('shift')->orderBy('sort_order')->orderBy('starts_at');
    }

    public function timeRange(): string
    {
        return substr((string) $this->starts_at, 0, 5).'–'.substr((string) $this->ends_at, 0, 5);
    }
}
