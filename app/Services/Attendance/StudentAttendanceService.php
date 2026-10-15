<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Section;
use App\Models\Attendance\StudentAttendance;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student attendance: class registers (daily or subject-wise), scans (QR / ID card / device),
 * register rules (today, limited back-dating, corrections for anything already recorded) and sheets.
 */
class StudentAttendanceService
{
    public function __construct(protected AttendanceSettings $settings) {}

    /** Teachers may mark only the sections they are class teacher of; admin users with the permission may mark any. */
    public function canMarkSection(User $user, Section $section): bool
    {
        if (! $user->checkPermissionTo('student-attendance.mark')) {
            return false;
        }

        return $user->portal() !== 'teacher' || $section->class_teacher_id === $user->id;
    }

    public function currentSession(): ?AcademicSession
    {
        return AcademicSession::query()->current()->first();
    }

    /**
     * Enrolled students of the section in the current session with their record for the date/slot.
     *
     * @return Collection<int,array{enrollment:StudentEnrollment,record:?StudentAttendance}>
     */
    public function register(Section $section, CarbonImmutable $date, ?int $subjectId = null): Collection
    {
        $session = $this->currentSession();
        if (! $session) {
            return collect();
        }

        $records = StudentAttendance::query()
            ->where('section_id', $section->id)
            ->whereDate('date', $date->toDateString())
            ->where('slot', StudentAttendance::slotFor($subjectId))
            ->get()->keyBy('student_id');

        return StudentEnrollment::query()
            ->with('student:id,name,name_bn,student_code,gender,photo_path')
            ->where('section_id', $section->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'enrolled')
            ->orderBy('roll_no')
            ->get()
            ->map(fn (StudentEnrollment $e) => ['enrollment' => $e, 'record' => $records->get($e->student_id)]);
    }

    /**
     * Save a register. $entries: [student_id => ['status' => …, 'remarks' => …]].
     * Rules: not in the future, not on a weekly holiday, inside the current open session;
     * today's register may be re-saved; an earlier day may be taken only if no record exists yet
     * and it is within the back-dating window — anything already recorded goes through a correction request.
     *
     * @return array{created:int, updated:int, unchanged:int}
     */
    public function save(Section $section, CarbonImmutable $date, array $entries, User $actor, ?int $subjectId = null, string $method = 'manual'): array
    {
        $session = $this->assertMarkable($date);
        $slot = StudentAttendance::slotFor($subjectId);
        $isToday = $date->isSameDay(CarbonImmutable::today());

        return DB::transaction(function () use ($section, $date, $entries, $actor, $subjectId, $method, $session, $slot, $isToday) {
            $enrollments = StudentEnrollment::query()
                ->where('section_id', $section->id)->where('academic_session_id', $session->id)->where('status', 'enrolled')
                ->get()->keyBy('student_id');

            $unknown = array_diff(array_map('intval', array_keys($entries)), $enrollments->keys()->all());
            if ($unknown) {
                throw ValidationException::withMessages(['entries' => __('Some students are not enrolled in this section.')]);
            }

            $existing = StudentAttendance::query()->where('section_id', $section->id)
                ->whereDate('date', $date->toDateString())->where('slot', $slot)->lockForUpdate()->get()->keyBy('student_id');

            if (! $isToday && $existing->isNotEmpty()) {
                throw ValidationException::withMessages(['date' => __('The register for :date is already taken. Use a correction request to change it.', ['date' => $date->toDateString()])]);
            }

            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($entries as $studentId => $entry) {
                $status = AttendanceStatus::from($entry['status']);
                $remarks = isset($entry['remarks']) && $entry['remarks'] !== '' ? mb_substr((string) $entry['remarks'], 0, 255) : null;
                $record = $existing->get((int) $studentId);

                if ($record) {
                    if ($record->status === $status && $record->remarks === $remarks) {
                        $result['unchanged']++;
                        continue;
                    }
                    $record->update(['status' => $status, 'remarks' => $remarks, 'marked_by' => $actor->id]);
                    $result['updated']++;
                    continue;
                }

                $enrollment = $enrollments->get((int) $studentId);
                StudentAttendance::query()->create([
                    'academic_session_id' => $session->id,
                    'student_id' => (int) $studentId,
                    'student_enrollment_id' => $enrollment->id,
                    'school_class_id' => $enrollment->school_class_id,
                    'section_id' => $section->id,
                    'subject_id' => $subjectId,
                    'slot' => $slot,
                    'date' => $date->toDateString(),
                    'status' => $status,
                    'method' => $method,
                    'remarks' => $remarks,
                    'marked_by' => $actor->id,
                ]);
                $result['created']++;
            }

            return $result;
        });
    }

    /**
     * Mark today's daily attendance from a scan (QR code / ID card barcode / device punch).
     *
     * @return array{ok:bool, state:string, message:string, student?:Student, record?:StudentAttendance}
     */
    public function scan(Student $student, ?User $actor, string $method = 'qr', ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $date = $at->startOfDay();

        if (! $student->isActive()) {
            return ['ok' => false, 'state' => 'inactive', 'message' => __(':name is not an active student.', ['name' => $student->name]), 'student' => $student];
        }
        if ($this->settings->isWeeklyHoliday($date)) {
            return ['ok' => false, 'state' => 'holiday', 'message' => __('Today is a weekly holiday.'), 'student' => $student];
        }

        $session = $this->currentSession();
        $enrollment = $session ? StudentEnrollment::query()->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)->where('status', 'enrolled')->first() : null;
        if (! $enrollment) {
            return ['ok' => false, 'state' => 'not_enrolled', 'message' => __(':name is not enrolled in the current session.', ['name' => $student->name]), 'student' => $student];
        }

        return DB::transaction(function () use ($student, $actor, $method, $at, $date, $session, $enrollment) {
            $existing = StudentAttendance::query()->where('student_id', $student->id)
                ->whereDate('date', $date->toDateString())->where('slot', 'day')->lockForUpdate()->first();

            if ($existing) {
                return ['ok' => true, 'state' => 'duplicate', 'student' => $student, 'record' => $existing,
                    'message' => __(':name is already marked :status today.', ['name' => $student->name, 'status' => $existing->status->label()])];
            }

            $start = CarbonImmutable::parse($date->toDateString().' '.$this->settings->get('student_start'));
            $late = $at->greaterThan($start->addMinutes((int) $this->settings->get('student_grace_minutes')));

            $record = StudentAttendance::query()->create([
                'academic_session_id' => $session->id,
                'student_id' => $student->id,
                'student_enrollment_id' => $enrollment->id,
                'school_class_id' => $enrollment->school_class_id,
                'section_id' => $enrollment->section_id,
                'slot' => 'day',
                'date' => $date->toDateString(),
                'status' => $late ? AttendanceStatus::Late : AttendanceStatus::Present,
                'check_in_at' => $at->format('H:i:s'),
                'method' => $method,
                'marked_by' => $actor?->id,
            ]);

            return ['ok' => true, 'state' => 'marked', 'student' => $student, 'record' => $record,
                'message' => __(':name marked :status at :time.', ['name' => $student->name, 'status' => $record->status->label(), 'time' => $at->format('H:i')])];
        });
    }

    /**
     * Month grid for a section: rows = enrolled students, columns = days.
     *
     * @return array{days:array<int,CarbonImmutable>, rows:Collection, holidays:array<string,bool>}
     */
    public function monthlySheet(Section $section, int $year, int $month, ?int $subjectId = null): array
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

        $records = StudentAttendance::query()->where('section_id', $section->id)
            ->where('slot', StudentAttendance::slotFor($subjectId))
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->get(['student_id', 'date', 'status']);

        $byStudent = [];
        foreach ($records as $r) {
            $byStudent[$r->student_id][$r->date->toDateString()] = $r->status;
        }

        $students = StudentEnrollment::query()->with('student:id,name,student_code')
            ->where('section_id', $section->id)
            ->whereIn('academic_session_id', AcademicSession::query()
                ->whereDate('starts_on', '<=', $to->toDateString())->whereDate('ends_on', '>=', $from->toDateString())->select('id'))
            ->orderBy('roll_no')->get();

        $rows = $students->map(function (StudentEnrollment $e) use ($byStudent) {
            $marks = $byStudent[$e->student_id] ?? [];
            $counts = array_fill_keys(AttendanceStatus::values(), 0);
            foreach ($marks as $status) {
                $counts[$status->value]++;
            }

            return ['enrollment' => $e, 'marks' => $marks, 'counts' => $counts, 'percentage' => AttendanceStatus::percentage($counts)];
        });

        return ['days' => $days, 'rows' => $rows, 'holidays' => $holidays];
    }

    /** Status → count for the daily registers of the branch on a date (dashboard). */
    public function dayCounts(CarbonImmutable $date, array $branchIds): array
    {
        return StudentAttendance::query()->whereIn('branch_id', $branchIds)
            ->whereDate('date', $date->toDateString())->where('slot', 'day')
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')
            ->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
    }

    /** Validates date rules and returns the current session. */
    public function assertMarkable(CarbonImmutable $date): AcademicSession
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

        $session = $this->currentSession();
        if (! $session || ! $session->isOpen()) {
            throw ValidationException::withMessages(['date' => __('There is no open current academic session.')]);
        }
        if ($date->lessThan(CarbonImmutable::parse($session->starts_on)) || $date->greaterThan(CarbonImmutable::parse($session->ends_on))) {
            throw ValidationException::withMessages(['date' => __('The date is outside the current academic session.')]);
        }

        return $session;
    }
}
