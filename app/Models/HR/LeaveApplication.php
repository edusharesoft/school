<?php

namespace App\Models\HR;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = [
        'employee_id', 'leave_type_id', 'from_date', 'to_date', 'days', 'is_half_day', 'reason',
        'status', 'applied_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'days' => 'float',
            'is_half_day' => 'boolean',
            'reviewed_at' => 'datetime',
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'applied_by' => 'integer',
            'reviewed_by' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /** Applications (pending or approved) that overlap the given range. */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function tone(): string
    {
        return match ($this->status) {
            'approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning', default => 'secondary',
        };
    }
}
