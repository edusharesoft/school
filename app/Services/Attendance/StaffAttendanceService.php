<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance\StaffAttendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teacher & employee attendance: daily register, check-in / check-out punches (QR, ID card, biometric),
 * late arrival, early departure, worked time and overtime, approved-leave adjustment, monthly sheet.
 */
class StaffAttendanceService
{
    /** Two punches closer than this are treated as the same punch. */
    public const DUPLICATE_PUNCH_MINUTES = 2;

    public function __construct(protected AttendanceSettings $settings) {}

    /**
     * Active employees with their record and approved leave for the date.
     *
     * @return Collection<int,array{employee:Employee,record:?StaffAttendance,leave:?LeaveApplication}>
     */
    public function register(CarbonImmutable $date, ?string $type = null, ?int $departmentId = null): Collection
    {
        $records = StaffAttendance::query()->whereDate('date', $date->toDateString())->get()->keyBy('employee_id');
        $leaves = LeaveApplication::query()->with('leaveType:id,name')->where('status', 'approved')
            ->whereDate('from_date', '<=', $date->toDateString())->whereDate('to_date', '>=', $date->toDateString())
            ->get()->keyBy('employee_id');

        return Employee::query()->with('designation:id,name,rank')
            ->where('status', 'active')
            ->whereDate('joining_date', '<=', $date->toDateString())
            ->when($type, fn ($q) => $q->where('employee_type', $type))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('employee_type')->orderBy('name')
            ->get()
            ->map(fn (Employee $e) => ['employee' => $e, 'record' => $records->get($e->id), 'leave' => $leaves->get($e->id)]);
    }

    /**
     * Save the day register. $entries: [employee_id => ['status','check_in'(HH:MM)?,'check_out'?,'remarks'?]].
     * Past days: only employees without a record may be added (within the back-dating window);
     * existing past records are changed through correction requests.
     *
     * @return array{created:int, updated:int, unchanged:int, skipped:int}
     */
    public function saveDay(CarbonImmutable $date, array $entries, User $actor): array
    {
        $this->assertMarkable($date);
        $isToday = $date->isSameDay(CarbonImmutable::today());

        return DB::transaction(function () use ($date, $entries, $actor, $isToday) {
            $employees = Employee::query()->whereIn('id', array_map('intval', array_keys($entries)))->where('status', 'active')->get()->keyBy('id');
            if ($employees->count() !== count($entries)) {
                throw ValidationException::withMessages(['entries' => __('Some employees are unknown or not active.')]);
            }

            $existing = StaffAttendance::query()->whereIn('employee_id', $employees->keys())
                ->whereDate('date', $date->toDateString())->lockForUpdate()->get()->keyBy('employee_id');

            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];

            foreach ($entries as $employeeId => $entry) {
                $record = $existing->get((int) $employeeId);
                if ($record && ! $isToday) {
                    $result['skipped']++; // past record → correction request
                    continue;
                }

                $attributes = $this->attributesFor($date, $entry);
                $attributes['marked_by'] = $actor->id;

                if ($record) {
                    // Approved leave written by the leave workflow is not overwritten from the register.
                    if ($record->leave_application_id && $record->status === AttendanceStatus::Leave) {
                        $result['skipped']++;
                        continue;
                    }
                    $record->fill($attributes);
                    if (! $record->isDirty(['status', 'check_in_at', 'check_out_at', 'remarks'])) {
                        $result['unchanged']++;
                        continue;
                    }
                    $record->save();
                    $result['updated']++;
                    continue;
                }

                StaffAttendance::query()->create($attributes + ['employee_id' => (int) $employeeId, 'date' => $date->toDateString(), 'method' => 'manual']);
                $result['created']++;
            }

            return $result;
        });
    }

    /**
     * A check-in/out punch (scan or device). First punch of the day = check-in, later punches move check-out.
     *
     * @return array{ok:bool, state:string, message:string, record?:StaffAttendance}
     */
    public function punch(Employee $employee, CarbonImmutable $at, string $method, ?User $actor = null): array
    {
        if (! $employee->isActive()) {
            return ['ok' => false, 'state' => 'inactive', 'message' => __(':name is not an active employee.', ['name' => $employee->name])];
        }

        $date = $at->startOfDay();

        return DB::transaction(function () use ($employee, $at, $date, $method, $actor) {
            $record = StaffAttendance::query()->where('employee_id', $employee->id)
                ->whereDate('date', $date->toDateString())->lockForUpdate()->first();

            if ($record && $record->status === AttendanceStatus::Leave) {
                return ['ok' => false, 'state' => 'on_leave', 'record' => $record, 'message' => __(':name is on approved leave today.', ['name' => $employee->name])];
            }

            if (! $record || ! $record->check_in_at) {
                $attributes = $this->timings($date, $at, null) + ['check_in_at' => $at, 'method' => $method, 'marked_by' => $actor?->id];
                $attributes['status'] = $attributes['late_minutes'] > 0 ? AttendanceStatus::Late : AttendanceStatus::Present;

                if ($record) {
                    $record->update($attributes);
                } else {
                    $record = StaffAttendance::query()->create($attributes + ['employee_id' => $employee->id, 'date' => $date->toDateString()]);
                }

                return ['ok' => true, 'state' => 'check_in', 'record' => $record,
                    'message' => __(':name checked in at :time (:status).', ['name' => $employee->name, 'time' => $at->format('H:i'), 'status' => $record->status->label()])];
            }

            $checkIn = CarbonImmutable::parse($record->check_in_at);
            $lastPunch = $record->check_out_at ? CarbonImmutable::parse($record->check_out_at) : $checkIn;
            if ($at->lessThanOrEqualTo($lastPunch->addMinutes(self::DUPLICATE_PUNCH_MINUTES))) {
                return ['ok' => true, 'state' => 'duplicate', 'record' => $record, 'message' => __('Punch already recorded for :name.', ['name' => $employee->name])];
            }

            $timings = $this->timings($date, $checkIn, $at);
            $status = $record->status;
            if ($timings['worked_minutes'] < (int) $this->settings->get('half_day_minutes') && in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true)) {
                $status = AttendanceStatus::HalfDay;
            } elseif ($status === AttendanceStatus::HalfDay && ! $record->leave_application_id) {
                $status = $timings['late_minutes'] > 0 ? AttendanceStatus::Late : AttendanceStatus::Present;
            }

            $record->update($timings + ['check_out_at' => $at, 'status' => $status]);

            return ['ok' => true, 'state' => 'check_out', 'record' => $record,
                'message' => __(':name checked out at :time.', ['name' => $employee->name, 'time' => $at->format('H:i')])];
        });
    }

    /**
     * Late / early-leave / overtime / worked minutes for a day.
     *
     * @return array{late_minutes:int, early_leave_minutes:int, overtime_minutes:int, worked_minutes:int}
     */
    public function timings(CarbonImmutable $date, ?CarbonImmutable $in, ?CarbonImmutable $out): array
    {
        $start = CarbonImmutable::parse($date->toDateString().' '.$this->settings->get('staff_start'));
        $end = CarbonImmutable::parse($date->toDateString().' '.$this->settings->get('staff_end'));

        $late = $in && $in->greaterThan($start->addMinutes((int) $this->settings->get('staff_grace_minutes')))
            ? (int) $start->diffInMinutes($in) : 0;

        $early = 0;
        $overtime = 0;
        $worked = 0;
        if ($in && $out && $out->greaterThan($in)) {
            $worked = (int) $in->diffInMinutes($out);
            if ($out->lessThan($end->subMinutes((int) $this->settings->get('early_leave_grace_minutes')))) {
                $early = (int) $out->diffInMinutes($end);
            }
            if ($out->greaterThan($end)) {
                $extra = (int) $end->diffInMinutes($out);
                $overtime = $extra >= (int) $this->settings->get('overtime_min_minutes') ? $extra : 0;
            }
        }

        return ['late_minutes' => $late, 'early_leave_minutes' => $early, 'overtime_minutes' => $overtime, 'worked_minutes' => $worked];
    }

    /**
     * Month grid: rows = employees, columns = days.
     *
     * @return array{days:array<int,CarbonImmutable>, rows:Collection, holidays:array<string,bool>}
     */
    public function monthlySheet(int $year, int $month, ?string $type = null): array
    {
        $from = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $to = $from->endOfMonth()->startOfDay();
        $days = [];
        $holidays = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $d = CarbonImmutable::parse($day);
            $days[(int) $d->format('j')] = $d;
            $holidays[$d->toDateString()] = $this->settings->isWeeklyHoliday($d);
        }

        $records = StaffAttendance::query()
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->get(['employee_id', 'date', 'status', 'late_minutes', 'overtime_minutes']);

        $byEmployee = [];
        $minutes = [];
        foreach ($records as $r) {
            $byEmployee[$r->employee_id][$r->date->toDateString()] = $r->status;
            $minutes[$r->employee_id]['late'] = ($minutes[$r->employee_id]['late'] ?? 0) + $r->late_minutes;
            $minutes[$r->employee_id]['overtime'] = ($minutes[$r->employee_id]['overtime'] ?? 0) + $r->overtime_minutes;
        }

        $rows = Employee::query()->with('designation:id,name')
            ->when($type, fn ($q) => $q->where('employee_type', $type))
            ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', array_keys($byEmployee)))
            ->orderBy('employee_type')->orderBy('name')->get()
            ->map(function (Employee $e) use ($byEmployee, $minutes) {
                $marks = $byEmployee[$e->id] ?? [];
                $counts = array_fill_keys(AttendanceStatus::values(), 0);
                foreach ($marks as $status) {
                    $counts[$status->value]++;
                }

                return [
                    'employee' => $e, 'marks' => $marks, 'counts' => $counts,
                    'percentage' => AttendanceStatus::percentage($counts),
                    'late_minutes' => $minutes[$e->id]['late'] ?? 0,
                    'overtime_minutes' => $minutes[$e->id]['overtime'] ?? 0,
                ];
            });

        return ['days' => $days, 'rows' => $rows, 'holidays' => $holidays];
    }

    public function assertMarkable(CarbonImmutable $date): void
    {
        $today = CarbonImmutable::today();
        if ($date->greaterThan($today)) {
            throw ValidationException::withMessages(['date' => __('Attendance cannot be taken for a future date.')]);
        }
        if ($date->lessThan($today->subDays((int) $this->settings->get('backdate_days')))) {
            throw ValidationException::withMessages(['date' => __('This date is outside the allowed back-dating window. Use a correction request.')]);
        }
        if ($this->settings->isWeeklyHoliday($date)) {
            throw ValidationException::withMessages(['date' => __(':date is a weekly holiday.', ['date' => $date->toDateString()])]);
        }
    }

    /** Register row → attributes (manual status wins; times are optional). */
    protected function attributesFor(CarbonImmutable $date, array $entry): array
    {
        $status = AttendanceStatus::from($entry['status']);
        $in = ! empty($entry['check_in']) ? CarbonImmutable::parse($date->toDateString().' '.$entry['check_in']) : null;
        $out = ! empty($entry['check_out']) ? CarbonImmutable::parse($date->toDateString().' '.$entry['check_out']) : null;

        if (in_array($status, [AttendanceStatus::Absent, AttendanceStatus::Leave, AttendanceStatus::Excused], true)) {
            $in = $out = null;
        }
        if ($in && $out && $out->lessThanOrEqualTo($in)) {
            throw ValidationException::withMessages(['entries' => __('Check-out must be after check-in.')]);
        }

        return $this->timings($date, $in, $out) + [
            'status' => $status,
            'check_in_at' => $in,
            'check_out_at' => $out,
            'remarks' => isset($entry['remarks']) && $entry['remarks'] !== '' ? mb_substr((string) $entry['remarks'], 0, 255) : null,
        ];
    }
}
