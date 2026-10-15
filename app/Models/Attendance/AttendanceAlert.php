<?php

namespace App\Models\Attendance;

use App\Models\Academic\Section;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Consecutive-absence alert. One row per streak (keyed by the streak's first absent day). */
class AttendanceAlert extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'student_id', 'section_id', 'streak_days', 'first_absent_on', 'last_absent_on', 'status',
        'acknowledged_by', 'acknowledged_at', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'first_absent_on' => 'date',
            'last_absent_on' => 'date',
            'acknowledged_at' => 'datetime',
            'notified_at' => 'datetime',
            'streak_days' => 'integer',
            'student_id' => 'integer',
            'section_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
