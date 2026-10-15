<?php

namespace App\Services\Exams;

use App\Enums\AttendanceStatus;
use App\Events\Exams\ExamResultsPublished;
use App\Models\Attendance\StudentAttendance;
use App\Models\Exams\ExamMark;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Result processing and the approval workflow:
 *   marks_entry ──process──▶ processing ──approve──▶ approved ──publish──▶ published
 *   processing ──reopen──▶ marks_entry        published ──unpublish──▶ approved
 * Re-processing (e.g. after an approved mark correction) always drops the exam back to "processing",
 * so changed results are approved and published again. Positions use competition ranking (1, 2, 2, 4):
 * passed students first, then GPA, then total marks; incomplete results get no position.
 */
class ResultService
{
    public function __construct(protected GradingService $grading, protected AuditLogger $audit) {}

    /** @return array{students:int, pass:int, fail:int, incomplete:int} */
    public function process(Examination $exam, ?User $user = null): array
    {
        if (! in_array($exam->status, ['marks_entry', 'processing', 'approved', 'published'], true)) {
            throw ValidationException::withMessages(['status' => __('Results can be processed once marks entry has started.')]);
        }

        $scale = $exam->gradingScale()->withTrashed()->firstOrFail();
        $rules = $scale->rules();
        $papers = $exam->subjects()->with('subject:id,name,code')->get()->groupBy('school_class_id');
        $marks = ExamMark::query()->where('examination_id', $exam->id)->get()->keyBy(fn ($m) => $m->examination_subject_id.'-'.$m->student_id);
        $withMarks = $marks->pluck('student_id')->unique()->values();

        $rows = [];
        foreach ($papers as $classId => $classPapers) {
            $enrollments = StudentEnrollment::query()->where('academic_session_id', $exam->academic_session_id)->where('school_class_id', $classId)
                ->where(fn ($q) => $q->whereNotIn('status', MarkEntryService::LEFT)->orWhereIn('student_id', $withMarks))->get();

            foreach ($enrollments as $enrollment) {
                $subjects = [];
                foreach ($classPapers as $paper) {
                    if ($paper->study_group_id && (int) $paper->study_group_id !== (int) $enrollment->study_group_id) {
                        continue;
                    }
                    $mark = $marks->get($paper->id.'-'.$enrollment->student_id);
                    if (! $mark && $paper->is_optional) {
                        continue; // optional (4th) subject not taken
                    }
                    $components = $paper->activeComponents();
                    $graded = $mark
                        ? $this->grading->subject($components, (float) $paper->pass_marks, $mark->marks ?? [], $mark->is_absent, $rules['grades'])
                        : ['status' => 'incomplete', 'total' => null, 'percent' => null, 'grade' => null, 'point' => null, 'failed_components' => []];

                    $subjects[] = [
                        'paper_id' => $paper->id,
                        'subject_id' => $paper->subject_id,
                        'subject' => $paper->subject?->name,
                        'code' => $paper->subject?->code,
                        'full' => (float) $paper->full_marks,
                        'components' => $components,
                        'marks' => $mark?->marks,
                        'absent' => (bool) $mark?->is_absent,
                        'is_optional' => (bool) $paper->is_optional,
                        'counts_in_gpa' => (bool) $paper->counts_in_gpa,
                    ] + $graded;
                }
                if ($subjects === []) {
                    continue;
                }

                $gpa = $this->grading->gpa($subjects, $rules);
                $total = round(array_sum(array_map(fn ($s) => (float) ($s['total'] ?? 0), $subjects)), 2);
                $full = round(array_sum(array_column($subjects, 'full')), 2);

                $rows[] = [
                    'examination_id' => $exam->id,
                    'student_id' => $enrollment->student_id,
                    'student_enrollment_id' => $enrollment->id,
                    'school_class_id' => $enrollment->school_class_id,
                    'section_id' => $enrollment->section_id,
                    'study_group_id' => $enrollment->study_group_id,
                    'roll_no' => $enrollment->roll_no,
                    'total_marks' => $total,
                    'full_marks' => $full,
                    'percentage' => $full > 0 ? round($total / $full * 100, 2) : null,
                    'gpa' => $gpa['gpa'],
                    'gpa_without_optional' => $gpa['gpa_without_optional'],
                    'grade' => $gpa['grade'],
                    'status' => $gpa['status'],
                    'failed_subjects' => $gpa['status'] === 'fail'
                        ? count(array_filter($subjects, fn ($s) => in_array($s['status'], ['fail', 'absent'], true) && empty($s['is_optional'])))
                        : 0,
                    'class_position' => null,
                    'section_position' => null,
                    'subjects' => $subjects,
                ];
            }
        }

        $this->rank($rows, 'class_position', fn ($r) => (string) $r['school_class_id']);
        $this->rank($rows, 'section_position', fn ($r) => $r['school_class_id'].'-'.$r['section_id']);

        $attendance = $this->attendance(array_column($rows, 'student_id'), $exam);
        $remarks = collect($rules['grades'])->pluck('remark', 'grade');

        DB::transaction(function () use ($exam, $rows, $attendance, $remarks, $user) {
            ExamResult::query()->where('examination_id', $exam->id)->delete();
            foreach ($rows as $row) {
                $row['attendance_pct'] = $attendance[$row['student_id']] ?? null;
                $row['remarks'] = match ($row['status']) {
                    'pass' => $remarks[$row['grade']] ?? null,
                    'fail' => trans_choice('Failed in :count subject|Failed in :count subjects', $row['failed_subjects'], ['count' => $row['failed_subjects']]),
                    default => __('Result incomplete — marks missing'),
                };
                ExamResult::query()->create($row);
            }

            $previous = $exam->status;
            $exam->forceFill(['status' => 'processing', 'approved_by' => null, 'approved_at' => null, 'published_at' => null])->save();
            $this->audit->log('examination.results_processed', $exam, ['status' => $previous], ['status' => 'processing', 'students' => count($rows)], userId: $user?->id);
        });

        $statuses = array_count_values(array_column($rows, 'status'));

        return ['students' => count($rows), 'pass' => $statuses['pass'] ?? 0, 'fail' => $statuses['fail'] ?? 0, 'incomplete' => $statuses['incomplete'] ?? 0];
    }

    public function approve(Examination $exam, User $user): void
    {
        if ($exam->status !== 'processing' || ! $exam->results()->exists()) {
            throw ValidationException::withMessages(['status' => __('Only processed results can be approved.')]);
        }
        $exam->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        $this->audit->log('examination.results_approved', $exam, ['status' => 'processing'], ['status' => 'approved'], userId: $user?->id);
    }

    public function publish(Examination $exam, User $user): void
    {
        if ($exam->status !== 'approved') {
            throw ValidationException::withMessages(['status' => __('Results must be approved before they are published.')]);
        }
        $exam->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $this->audit->log('examination.results_published', $exam, ['status' => 'approved'], ['status' => 'published'], userId: $user?->id);

        ExamResultsPublished::dispatch($exam);
    }

    public function unpublish(Examination $exam, User $user): void
    {
        if ($exam->status !== 'published') {
            throw ValidationException::withMessages(['status' => __('These results are not published.')]);
        }
        $exam->forceFill(['status' => 'approved', 'published_at' => null])->save();
        $this->audit->log('examination.results_unpublished', $exam, ['status' => 'published'], ['status' => 'approved'], userId: $user?->id);
    }

    /** Back to marks entry (results are discarded and must be processed again). */
    public function reopen(Examination $exam, User $user): void
    {
        if ($exam->status !== 'processing') {
            throw ValidationException::withMessages(['status' => __('Only an exam in processing can be reopened for marks entry.')]);
        }
        DB::transaction(function () use ($exam, $user) {
            ExamResult::query()->where('examination_id', $exam->id)->delete();
            $exam->forceFill(['status' => 'marks_entry'])->save();
            $this->audit->log('examination.marks_reopened', $exam, ['status' => 'processing'], ['status' => 'marks_entry'], userId: $user?->id);
        });
    }

    /**
     * Attendance % (day registers) from the session start until the exam starts (or today, whichever is earlier).
     *
     * @param  int[]  $studentIds
     * @return array<int,?float>
     */
    public function attendance(array $studentIds, Examination $exam): array
    {
        if ($studentIds === []) {
            return [];
        }
        $exam->loadMissing('session');
        $until = CarbonImmutable::today();
        if ($exam->starts_on && $exam->starts_on->lt($until)) {
            $until = CarbonImmutable::parse($exam->starts_on);
        }
        $from = $exam->session?->starts_on ? CarbonImmutable::parse($exam->session->starts_on) : $until->subYear();

        $counts = [];
        foreach (array_chunk(array_unique($studentIds), 500) as $chunk) {
            StudentAttendance::query()->whereIn('student_id', $chunk)->where('slot', 'day')
                ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $until->toDateString())
                ->selectRaw('student_id, status, COUNT(*) as n')->groupBy('student_id', 'status')->toBase()->get()
                ->each(function ($r) use (&$counts) {
                    $counts[(int) $r->student_id][(string) $r->status] = (int) $r->n;
                });
        }

        $out = [];
        foreach ($studentIds as $id) {
            $out[$id] = isset($counts[$id]) ? AttendanceStatus::percentage($counts[$id]) : null;
        }

        return $out;
    }

    /** Competition ranking inside each group; incomplete results are not ranked. */
    protected function rank(array &$rows, string $field, callable $group): void
    {
        $groups = [];
        foreach ($rows as $i => $row) {
            if ($row['status'] !== 'incomplete') {
                $groups[$group($row)][] = $i;
            }
        }

        foreach ($groups as $indexes) {
            $key = fn (int $i) => [$rows[$i]['status'] === 'pass' ? 0 : 1, -(float) ($rows[$i]['gpa'] ?? 0), -(float) $rows[$i]['total_marks']];
            usort($indexes, fn ($a, $b) => [$key($a), $rows[$a]['roll_no'] ?? PHP_INT_MAX] <=> [$key($b), $rows[$b]['roll_no'] ?? PHP_INT_MAX]);

            $position = 0;
            $previous = null;
            foreach ($indexes as $n => $i) {
                if ($key($i) !== $previous) {
                    $position = $n + 1;
                    $previous = $key($i);
                }
                $rows[$i][$field] = $position;
            }
        }
    }
}
