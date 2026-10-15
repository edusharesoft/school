<?php

namespace App\Models\HR;

use App\Models\Branch;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An employee of one branch assigned to work in another branch of the same institution (managed centrally). */
class StaffBranchAssignment extends Model
{
    use Auditable, BelongsToInstitution;

    protected $fillable = ['employee_id', 'branch_id', 'duty', 'starts_on', 'ends_on', 'assigned_by'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'employee_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(BranchScope::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->whereDate('starts_on', '<=', $today)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    public function isCurrent(): bool
    {
        return $this->starts_on->lte(today()) && ($this->ends_on === null || $this->ends_on->gte(today()));
    }
}
