<?php

namespace App\Models\Students;

use App\Models\Branch;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transfer of a student between two branches of the same institution. Visible to (and only to)
 * the two branches involved; the source branch requests, the target branch accepts or rejects.
 */
class StudentTransfer extends Model
{
    use Auditable, BelongsToInstitution;

    protected $fillable = ['from_branch_id', 'to_branch_id', 'student_id', 'requested_on', 'reason', 'requested_by'];

    protected function casts(): array
    {
        return [
            'requested_on' => 'date',
            'decided_at' => 'datetime',
            'from_branch_id' => 'integer',
            'to_branch_id' => 'integer',
            'student_id' => 'integer',
            'new_student_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function scopeInvolvingBranch(Builder $query, int $branchId): Builder
    {
        return $query->where(fn ($q) => $q->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId));
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /** The source-branch record, readable from the target branch only through this (institution-scoped) transfer. */
    public function sourceStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id')->withoutGlobalScope(BranchScope::class);
    }

    public function newStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'new_student_id')->withoutGlobalScope(BranchScope::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function involves(int $branchId): bool
    {
        return $this->from_branch_id === $branchId || $this->to_branch_id === $branchId;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function tone(): string
    {
        return match ($this->status) {
            'accepted' => 'success', 'rejected' => 'danger', 'pending' => 'warning', default => 'secondary',
        };
    }
}
