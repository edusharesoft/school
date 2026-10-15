<?php

namespace App\Services\HR;

use App\Enums\AttendanceStatus;
use App\Models\Attendance\StaffAttendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\Attendance\AttendanceSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leave workflow: apply → approve / reject, cancel. Approved leave is written into staff attendance
 * ("leave adjustment", master prompt §11) and reverted if an approved leave is cancelled.
 */
class LeaveService
{
    public function __construct(protected AttendanceSettings $settings) {}

    /** Working days in the range (weekly holidays excluded); a half day counts 0.5. */
    public function workingDays(CarbonImmutable $from, CarbonImmutable $to, bool $halfDay = false): float
    {
        if ($halfDay) {
            return $this->settings->isWeeklyHoliday($from) ? 0.0 : 0.5;
        }

        $days = 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            if (! $this->settings->isWeeklyHoliday($day)) {
                $days++;
            }
        }

        return (float) $days;
    }

    public function apply(Employee $employee, LeaveType $type, string $from, string $to, bool $halfDay, string $reason, User $applicant): LeaveApplication
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = $halfDay ? $from : CarbonImmutable::parse($to)->startOfDay();

        $errors = [];
        if (! $employee->isActive()) {
            $errors['employee_id'] = __('Leave can only be requested for an active employee.');
        }
        if (! $type->is_active) {
            $errors['leave_type_id'] = __('This leave type is not available.');
        }
        if ($to->lessThan($from)) {
            $errors['to_date'] = __('The end date must be on or after the start date.');
        }
        if ($from->year !== $to->year) {
            $errors['to_date'] = __('A leave application cannot span two calendar years. Please apply separately for each year.');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $days = $this->workingDays($from, $to, $halfDay);
        if ($days <= 0) {
            throw ValidationException::withMessages(['from_date' => __('The selected dates are all weekly holidays.')]);
        }

        return DB::transaction(function () use ($employee, $type, $from, $to, $halfDay, $reason, $applicant, $days) {
            // Serialise applications of the same employee (overlap + balance checks are race-free).
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            if (LeaveApplication::query()->where('employee_id', $employee->id)
                ->overlapping($from->toDateString(), $to->toDateString())->exists()) {
                throw ValidationException::withMessages(['from_date' => __('These dates overlap another pending or approved leave application.')]);
            }

            $this->assertBalance($employee, $type, $from->year, $days);

            return LeaveApplication::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'days' => $days,
                'is_half_day' => $halfDay,
                'reason' => $reason,
                'status' => 'pending',
                'applied_by' => $applicant->id,
            ]);
        });
    }

    public function approve(LeaveApplication $application, User $reviewer, ?string $note = null): LeaveApplication
    {
        $this->assertReviewable($application, $reviewer);

        return DB::transaction(function () use ($application, $reviewer, $note) {
            $application = LeaveApplication::query()->with(['employee', 'leaveType'])->lockForUpdate()->findOrFail($application->id);
            if (! $application->isPending()) {
                throw ValidationException::withMessages(['status' => __('Only pending applications can be reviewed.')]);
            }

            $this->assertBalance($application->employee, $application->leaveType, $application->from_date->year, $application->days, $application->id);

            $application->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $this->applyToAttendance($application, $reviewer);

            return $application;
        });
    }

    public function reject(LeaveApplication $application, User $reviewer, ?string $note = null): LeaveApplication
    {
        $this->assertReviewable($application, $reviewer);

        if (! $application->isPending()) {
            throw ValidationException::withMessages(['status' => __('Only pending applications can be reviewed.')]);
        }

        $application->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);

        return $application;
    }

    /** Applicant may cancel a pending application; an approver may also cancel an approved one (attendance is reverted). */
    public function cancel(LeaveApplication $application, User $actor): LeaveApplication
    {
        if (! in_array($application->status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages(['status' => __('This application can no longer be cancelled.')]);
        }

        return DB::transaction(function () use ($application, $actor) {
            if ($application->status === 'approved') {
                // Remove the leave days written by the approval (days with a real check-in are kept).
                StaffAttendance::query()->where('leave_application_id', $application->id)
                    ->whereNull('check_in_at')->get()->each->delete();
            }

            $application->update(['status' => 'cancelled', 'review_note' => trim(($application->review_note ? $application->review_note.' · ' : '').__('Cancelled by :name', ['name' => $actor->name]))]);

            return $application;
        });
    }

    /**
     * Balance per leave type for the calendar year.
     *
     * @return Collection<int,array{type:LeaveType,allowed:?int,used:float,pending:float,remaining:?float}>
     */
    public function balances(Employee $employee, int $year): Collection
    {
        $totals = LeaveApplication::query()->where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereYear('from_date', $year)
            ->select('leave_type_id', 'status', DB::raw('SUM(days) as total'))
            ->groupBy('leave_type_id', 'status')->get();

        return LeaveType::query()->active()->orderBy('name')->get()->map(function (LeaveType $type) use ($totals) {
            $used = (float) $totals->where('leave_type_id', $type->id)->where('status', 'approved')->sum('total');
            $pending = (float) $totals->where('leave_type_id', $type->id)->where('status', 'pending')->sum('total');

            return [
                'type' => $type,
                'allowed' => $type->isUnlimited() ? null : $type->days_per_year,
                'used' => $used,
                'pending' => $pending,
                'remaining' => $type->isUnlimited() ? null : max(0, $type->days_per_year - $used - $pending),
            ];
        });
    }

    protected function assertBalance(Employee $employee, LeaveType $type, int $year, float $days, ?int $exceptId = null): void
    {
        if ($type->isUnlimited()) {
            return;
        }

        $taken = (float) LeaveApplication::query()->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereYear('from_date', $year)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->sum('days');

        if ($taken + $days > $type->days_per_year) {
            throw ValidationException::withMessages(['leave_type_id' => __('Not enough :type balance: :left day(s) left, :asked requested.', [
                'type' => $type->name, 'left' => max(0, $type->days_per_year - $taken), 'asked' => $days,
            ])]);
        }
    }

    protected function assertReviewable(LeaveApplication $application, User $reviewer): void
    {
        $application->loadMissing('employee');

        // No self-approval: the approver must be someone other than the employee on leave.
        if ($application->employee?->user_id === $reviewer->id && ! $reviewer->isSuperAdmin()) {
            throw ValidationException::withMessages(['status' => __('You cannot review your own leave application.')]);
        }
    }

    /** Write "leave" into staff attendance for each working day that is not already recorded as attended. */
    protected function applyToAttendance(LeaveApplication $application, User $reviewer): void
    {
        $from = CarbonImmutable::parse($application->from_date);
        $to = CarbonImmutable::parse($application->to_date);
        $status = $application->is_half_day ? AttendanceStatus::HalfDay : AttendanceStatus::Leave;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            if ($this->settings->isWeeklyHoliday($day)) {
                continue;
            }

            $existing = StaffAttendance::query()->where('employee_id', $application->employee_id)
                ->whereDate('date', $day->toDateString())->first();

            // Days the employee actually attended stay as they are.
            if ($existing && ! in_array($existing->status, [AttendanceStatus::Absent, AttendanceStatus::Leave], true)) {
                continue;
            }

            $attributes = [
                'status' => $status->value,
                'leave_application_id' => $application->id,
                'method' => $existing?->method ?? 'manual',
                'remarks' => __('Approved leave: :type', ['type' => $application->leaveType?->name]),
                'marked_by' => $reviewer->id,
            ];

            $existing
                ? $existing->update($attributes)
                : StaffAttendance::query()->create($attributes + ['employee_id' => $application->employee_id, 'date' => $day->toDateString()]);
        }
    }
}
