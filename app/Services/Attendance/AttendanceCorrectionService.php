<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\StaffAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Correction workflow for recorded attendance (master prompt §11): request → approve / reject.
 * The record changes only on approval; every step is audit-logged (models are Auditable).
 * A requester who also holds the approval permission gets the request approved at once
 * (still recorded as a request + approval for the audit trail).
 */
class AttendanceCorrectionService
{
    public function __construct(protected StaffAttendanceService $staff) {}

    public function request(StudentAttendance|StaffAttendance $record, AttendanceStatus $status, string $reason, User $actor, ?string $checkIn = null, ?string $checkOut = null): AttendanceCorrection
    {
        $isStaff = $record instanceof StaffAttendance;

        if (! $isStaff) {
            $checkIn = $checkOut = null;
        }
        if ($checkIn && $checkOut && strcmp($checkOut, $checkIn) <= 0) {
            throw ValidationException::withMessages(['requested_check_out' => __('Check-out must be after check-in.')]);
        }
        if ($record->status === $status && ! $checkIn && ! $checkOut) {
            throw ValidationException::withMessages(['requested_status' => __('The requested status is the same as the current one.')]);
        }
        if (AttendanceCorrection::query()->where('attendable_type', $record->getMorphClass())
            ->where('attendable_id', $record->id)->pending()->exists()) {
            throw ValidationException::withMessages(['requested_status' => __('A correction request for this record is already pending.')]);
        }

        $correction = AttendanceCorrection::query()->create([
            'attendable_type' => $record->getMorphClass(),
            'attendable_id' => $record->id,
            'current_status' => $record->status,
            'requested_status' => $status,
            'requested_check_in' => $checkIn,
            'requested_check_out' => $checkOut,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $actor->id,
        ]);

        $permission = $isStaff ? 'staff-attendance.approve-correction' : 'student-attendance.approve-correction';
        if ($actor->checkPermissionTo($permission)) {
            return $this->approve($correction, $actor, __('Auto-approved (requester holds the approval permission).'), allowSelf: true);
        }

        return $correction;
    }

    public function approve(AttendanceCorrection $correction, User $reviewer, ?string $note = null, bool $allowSelf = false): AttendanceCorrection
    {
        $this->assertReviewable($correction, $reviewer, $allowSelf);

        return DB::transaction(function () use ($correction, $reviewer, $note) {
            /** @var AttendanceCorrection $correction */
            $correction = AttendanceCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if (! $correction->isPending()) {
                throw ValidationException::withMessages(['status' => __('This request has already been reviewed.')]);
            }

            /** @var Model|null $record */
            $record = $correction->attendable()->lockForUpdate()->first();
            if (! $record) {
                throw ValidationException::withMessages(['status' => __('The attendance record no longer exists.')]);
            }

            $record instanceof StaffAttendance
                ? $this->applyToStaff($record, $correction, $reviewer)
                : $record->update(['status' => $correction->requested_status, 'marked_by' => $reviewer->id]);

            $correction->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);

            return $correction;
        });
    }

    public function reject(AttendanceCorrection $correction, User $reviewer, ?string $note = null): AttendanceCorrection
    {
        $this->assertReviewable($correction, $reviewer);
        if (! $correction->isPending()) {
            throw ValidationException::withMessages(['status' => __('This request has already been reviewed.')]);
        }

        $correction->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);

        return $correction;
    }

    protected function applyToStaff(StaffAttendance $record, AttendanceCorrection $correction, User $reviewer): void
    {
        $date = CarbonImmutable::parse($record->date);
        $status = $correction->requested_status;

        $in = $correction->requested_check_in ? CarbonImmutable::parse($date->toDateString().' '.$correction->requested_check_in)
            : ($record->check_in_at ? CarbonImmutable::parse($record->check_in_at) : null);
        $out = $correction->requested_check_out ? CarbonImmutable::parse($date->toDateString().' '.$correction->requested_check_out)
            : ($record->check_out_at ? CarbonImmutable::parse($record->check_out_at) : null);

        if (in_array($status, [AttendanceStatus::Absent, AttendanceStatus::Leave, AttendanceStatus::Excused], true)) {
            $in = $out = null;
        }

        $record->update($this->staff->timings($date, $in, $out) + [
            'status' => $status,
            'check_in_at' => $in,
            'check_out_at' => $out,
            'marked_by' => $reviewer->id,
        ]);
    }

    protected function assertReviewable(AttendanceCorrection $correction, User $reviewer, bool $allowSelf = false): void
    {
        $permission = $correction->isForStaff() ? 'staff-attendance.approve-correction' : 'student-attendance.approve-correction';
        if (! $reviewer->isSuperAdmin() && ! $reviewer->checkPermissionTo($permission)) {
            abort(403);
        }

        if (! $allowSelf && $correction->requested_by === $reviewer->id && ! $reviewer->isSuperAdmin()) {
            throw ValidationException::withMessages(['status' => __('You cannot review your own correction request.')]);
        }

        // A staff member may never approve a correction of their own attendance.
        if ($correction->isForStaff()) {
            $record = $correction->attendable()->with('employee:id,user_id')->first();
            if ($record?->employee?->user_id === $reviewer->id && ! $reviewer->isSuperAdmin()) {
                throw ValidationException::withMessages(['status' => __('You cannot approve a correction of your own attendance.')]);
            }
        }
    }
}
