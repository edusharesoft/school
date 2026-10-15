<?php

namespace App\Models\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\HR\Employee;
use App\Models\HR\LeaveApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** One row per employee per date. Late / early-leave / overtime minutes are calculated by StaffAttendanceService. */
class StaffAttendance extends Model
{
    use Auditable, BelongsToBranch;

    public const METHODS = ['manual', 'qr', 'id_card', 'biometric'];

    protected $fillable = [
        'employee_id', 'date', 'status', 'check_in_at', 'check_out_at', 'late_minutes', 'early_leave_minutes',
        'overtime_minutes', 'worked_minutes', 'method', 'leave_application_id', 'remarks', 'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'employee_id' => 'integer',
            'leave_application_id' => 'integer',
            'marked_by' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function corrections(): MorphMany
    {
        return $this->morphMany(AttendanceCorrection::class, 'attendable');
    }

    public function describe(): string
    {
        return $this->employee?->name.' — '.$this->date?->toDateString();
    }
}
