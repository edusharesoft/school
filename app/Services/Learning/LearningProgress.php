<?php

namespace App\Services\Learning;

use App\Models\Learning\Assignment;
use App\Models\Learning\AssignmentSubmission;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use Illuminate\Support\Collection;

/**
 * Learning progress shown to students, parents and teachers: hand-in discipline and marks for the current session
 * (assignments), extended by quizzes and online-class attendance when those modules have data.
 */
class LearningProgress
{
    /**
     * @return array{enrollment:?StudentEnrollment, total:int, done:int, pending:int, missing:int, late:int, evaluated:int, average:?float, subjects:array<int,array{name:string,average:float,count:int}>, items:Collection}
     */
    public function forStudent(Student $student): array
    {
        $enrollment = $student->currentEnrollment;
        $empty = ['enrollment' => $enrollment, 'total' => 0, 'done' => 0, 'pending' => 0, 'missing' => 0, 'late' => 0, 'evaluated' => 0, 'average' => null, 'subjects' => [], 'items' => collect(), 'classes' => ['total' => 0, 'attended' => 0, 'percent' => null], 'quizzes' => ['total' => 0, 'taken' => 0, 'average' => null]];
        if (! $enrollment) {
            return $empty;
        }

        $empty['classes'] = $this->classAttendance($student, $enrollment);
        $empty['quizzes'] = $this->quizSummary($student, $enrollment);
        $assignments = Assignment::query()->visibleToStudents()->forEnrollment($enrollment)->with('subject:id,name,name_bn')->orderBy('due_at')->get();
        $subs = AssignmentSubmission::query()->where('student_id', $student->id)->whereIn('assignment_id', $assignments->pluck('id'))->get()->keyBy('assignment_id');

        $out = $empty;
        $pcts = [];
        $bySubject = [];
        foreach ($assignments as $a) {
            $s = $subs->get($a->id);
            $done = $s && ($s->submitted_at !== null || $s->status === 'evaluated');
            $state = match (true) {
                $s?->status === 'evaluated' => 'evaluated',
                $s?->status === 'returned' => 'returned',
                $done => 'submitted',
                $a->isPastDue() => 'missing',
                default => 'pending',
            };
            $out['total']++;
            $done && $out['done']++;
            $state === 'pending' && $out['pending']++;
            ($state === 'missing' || $state === 'returned') && $out['missing']++;
            $s?->is_late && $out['late']++;
            if ($state === 'evaluated') {
                $out['evaluated']++;
                if ($a->isGraded() && $s->marks !== null && (float) $a->max_marks > 0) {
                    $pct = (float) $s->marks / (float) $a->max_marks * 100;
                    $pcts[] = $pct;
                    $bySubject[$a->subject_id ?? 0][] = [$a->subject?->displayName() ?? __('General'), $pct];
                }
            }
            $out['items']->push(['assignment' => $a, 'submission' => $s, 'state' => $state]);
        }
        $out['average'] = $pcts ? round(array_sum($pcts) / count($pcts), 1) : null;
        foreach ($bySubject as $rows) {
            $out['subjects'][] = ['name' => $rows[0][0], 'average' => round(array_sum(array_column($rows, 1)) / count($rows), 1), 'count' => count($rows)];
        }
        usort($out['subjects'], fn ($x, $y) => strcmp($x['name'], $y['name']));

        return $out;
    }

    /**
     * Online / offline class attendance of the current session. Only classes that have ended, were not cancelled and
     * whose register was taken count; "excused" is neither attended nor missed.
     *
     * @return array{total:int, attended:int, percent:?float}
     */
    public function classAttendance(Student $student, StudentEnrollment $enrollment): array
    {
        $classIds = \App\Models\Learning\OnlineClass::query()->forEnrollment($enrollment)->where('status', '!=', 'cancelled')
            ->where('ends_at', '<', now())->whereHas('attendances')->pluck('id');
        $marks = \App\Models\Learning\OnlineClassAttendance::query()->where('student_id', $student->id)->whereIn('online_class_id', $classIds)->pluck('status');
        $counted = $marks->reject(fn ($s) => $s === 'excused');
        $total = $classIds->count() - $marks->filter(fn ($s) => $s === 'excused')->count();
        $attended = $counted->filter(fn ($s) => in_array($s, ['present', 'late'], true))->count();

        return ['total' => max(0, $total), 'attended' => $attended, 'percent' => $total > 0 ? round($attended / $total * 100, 1) : null];
    }

    /**
     * Quizzes given to the student's class in the current session: how many were taken and the average of the best scores.
     *
     * @return array{total:int, taken:int, average:?float}
     */
    public function quizSummary(Student $student, StudentEnrollment $enrollment): array
    {
        $ids = \App\Models\Learning\Quiz::query()->visibleToStudents()->forEnrollment($enrollment)->pluck('id');
        $best = \App\Models\Learning\QuizAttempt::query()->where('student_id', $student->id)->whereIn('quiz_id', $ids)->whereNotNull('submitted_at')
            ->get()->groupBy('quiz_id')->map(fn ($a) => (float) $a->max('percent'));

        return ['total' => $ids->count(), 'taken' => $best->count(), 'average' => $best->isEmpty() ? null : round($best->avg(), 1)];
    }

    /**
     * Class overview for teachers: one line per student of the section.
     *
     * @return Collection<int,array{student:Student, total:int, done:int, missing:int, average:?float}>
     */
    public function forSection(int $sectionId): Collection
    {
        $enrollments = StudentEnrollment::query()->where('section_id', $sectionId)->where('status', 'enrolled')
            ->whereIn('academic_session_id', \App\Models\Academic\AcademicSession::query()->current()->select('id'))
            ->with('student:id,name,student_code')->get();
        if ($enrollments->isEmpty()) {
            return collect();
        }

        $first = $enrollments->first();
        $assignments = Assignment::query()->visibleToStudents()->where('academic_session_id', $first->academic_session_id)
            ->where('school_class_id', $first->school_class_id)->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $sectionId))->get();
        $subs = AssignmentSubmission::query()->whereIn('assignment_id', $assignments->pluck('id'))->whereIn('student_id', $enrollments->pluck('student_id'))->get()->groupBy('student_id');

        return $enrollments->map(function (StudentEnrollment $e) use ($assignments, $subs) {
            $mine = $subs->get($e->student_id, collect())->keyBy('assignment_id');
            $done = $missing = 0;
            $pcts = [];
            foreach ($assignments as $a) {
                $s = $mine->get($a->id);
                $isDone = $s && ($s->submitted_at !== null || $s->status === 'evaluated');
                $isDone ? $done++ : ($a->isPastDue() && $missing++);
                if ($s?->status === 'evaluated' && $s->marks !== null && $a->isGraded() && (float) $a->max_marks > 0) {
                    $pcts[] = (float) $s->marks / (float) $a->max_marks * 100;
                }
            }

            return ['student' => $e->student, 'roll' => $e->roll_no, 'total' => $assignments->count(), 'done' => $done, 'missing' => $missing,
                'average' => $pcts ? round(array_sum($pcts) / count($pcts), 1) : null];
        })->sortBy(fn ($r) => sprintf('%05d-%s', $r['roll'] ?? 99999, $r['student']?->name))->values();
    }
}
