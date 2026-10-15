<?php

namespace App\Services\Portal;

use App\Enums\AttendanceStatus;
use App\Models\Academic\AcademicSession;
use App\Models\Attendance\StudentAttendance;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Data for the student and parent portals: linked records, attendance, published results, upcoming exams. */
class PortalService
{
    public function studentFor(User $user): ?Student
    {
        return Student::query()->where('user_id', $user->id)->with(['currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name', 'currentEnrollment.studyGroup:id,name', 'currentEnrollment.session'])->first();
    }

    public function guardianFor(User $user): ?Guardian
    {
        return Guardian::query()->where('user_id', $user->id)->first();
    }

    /** @return Collection<int,Student> */
    public function children(Guardian $guardian): Collection
    {
        return $guardian->students()->with(['currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name', 'currentEnrollment.session'])
            ->orderBy('name')->get();
    }

    public function isChildOf(Guardian $guardian, Student $student): bool
    {
        return $guardian->students()->whereKey($student->id)->exists();
    }

    /** @return array{pct:?float, counts:array<string,int>} day-register attendance of the current session */
    public function attendance(Student $student, ?AcademicSession $session): array
    {
        $query = StudentAttendance::query()->where('student_id', $student->id)->where('slot', 'day');
        if ($session?->starts_on) {
            $query->whereDate('date', '>=', CarbonImmutable::parse($session->starts_on)->toDateString());
        }
        $counts = $query->selectRaw('status, COUNT(*) as n')->groupBy('status')->toBase()->pluck('n', 'status')->map(fn ($v) => (int) $v)->all();

        return ['pct' => AttendanceStatus::percentage($counts), 'counts' => $counts];
    }

    /** @return Collection<int,ExamResult> */
    public function publishedResults(Student $student, ?int $limit = null): Collection
    {
        return ExamResult::query()->published()->with(['examination:id,name,status,published_at,starts_on', 'schoolClass:id,name'])
            ->where('student_id', $student->id)->latest('id')->when($limit, fn ($q, $l) => $q->limit($l))->get();
    }

    /** Scheduled exams of the student's class (admit cards). @return Collection<int,Examination> */
    public function upcomingExams(Student $student): Collection
    {
        $enrollment = $student->currentEnrollment;
        if (! $enrollment) {
            return collect();
        }

        return Examination::query()->where('academic_session_id', $enrollment->academic_session_id)->whereIn('status', ['scheduled', 'marks_entry'])
            ->whereHas('subjects', fn ($q) => $q->where('school_class_id', $enrollment->school_class_id))
            ->orderBy('starts_on')->get();
    }

    /** Fee status for the portals: invoices (not cancelled), completed receipts, dues and advance. */
    public function fees(Student $student): array
    {
        $invoices = FeeInvoice::query()->where('student_id', $student->id)->where('status', '!=', 'cancelled')->latest('issue_date')->latest('id')->get();
        $payments = FeePayment::query()->where('student_id', $student->id)->where('status', 'completed')->latest('date')->latest('id')->get();

        return [
            'invoices' => $invoices,
            'payments' => $payments,
            'due' => round($invoices->whereIn('status', ['unpaid', 'partial'])->sum('balance'), 2),
            'overdue' => round($invoices->whereIn('status', ['unpaid', 'partial'])->filter(fn ($i) => $i->due_date->isPast())->sum('balance'), 2),
            'credit' => round($payments->sum('unallocated'), 2),
        ];
    }
}
