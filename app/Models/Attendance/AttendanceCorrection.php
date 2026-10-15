<?php

namespace App\Models\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Request to change a past attendance record; applied only after approval. */
class AttendanceCorrection extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'attendable_type', 'attendable_id', 'current_status', 'requested_status', 'requested_check_in', 'requested_check_out',
        'reason', 'status', 'requested_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'current_status' => AttendanceStatus::class,
            'requested_status' => AttendanceStatus::class,
            'reviewed_at' => 'datetime',
            'attendable_id' => 'integer',
            'requested_by' => 'integer',
            'reviewed_by' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isForStaff(): bool
    {
        return $this->attendable_type === 'staff_attendance';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
