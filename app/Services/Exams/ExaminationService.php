<?php

namespace App\Services\Exams;

use App\Models\Academic\ClassSubject;
use App\Models\Academic\SchoolClass;
use App\Models\Exams\Examination;
use App\Models\Exams\ExaminationSubject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exam set-up: classes & subject papers, components, schedule (with clash check), status transitions. */
class ExaminationService
{
    /** Status changes allowed from the exam screen; processing → approved → published run through results (Part 3C-2). */
    public const MANUAL_TRANSITIONS = [
        'draft' => ['scheduled'],
        'scheduled' => ['draft', 'marks_entry'],
        'marks_entry' => ['scheduled'],
    ];

    /**
     * Add every subject of a class (group-specific subjects included) as a paper of the exam.
     * Theory-only subjects: theory = full marks. Theory + practical: 75 % theory / 25 % practical.
     * Component pass marks default to 33 % (rounded up); the overall pass mark comes from the class subject.
     */
    public function addClass(Examination $exam, SchoolClass $class): int
    {
        return DB::transaction(function () use ($exam, $class) {
            $added = 0;
            $existing = $exam->subjects()->where('school_class_id', $class->id)->get()
                ->keyBy(fn ($s) => $s->subject_id.'-'.($s->study_group_id ?? 0));

            $classSubjects = ClassSubject::query()->with('subject:id,type')->where('school_class_id', $class->id)->orderBy('sort_order')->get();
            if ($classSubjects->isEmpty()) {
                throw ValidationException::withMessages(['school_class_id' => __('This class has no subjects yet. Add them under Academics → Classes.')]);
            }

            foreach ($classSubjects as $i => $cs) {
                if ($existing->has($cs->subject_id.'-'.($cs->study_group_id ?? 0))) {
                    continue;
                }
                $full = (float) $cs->full_marks;
                $practical = $cs->subject?->type === 'theory_practical' ? round($full * 0.25) : 0.0;
                $theory = $full - $practical;

                $exam->subjects()->create([
                    'school_class_id' => $class->id,
                    'subject_id' => $cs->subject_id,
                    'study_group_id' => $cs->study_group_id,
                    'components' => array_filter([
                        'theory' => ['full' => $theory, 'pass' => ceil($theory * 0.33)],
                        'practical' => $practical > 0 ? ['full' => $practical, 'pass' => ceil($practical * 0.33)] : null,
                    ]),
                    'full_marks' => $full,
                    'pass_marks' => (float) $cs->pass_marks,
                    'is_optional' => $cs->category === 'optional',
                    'counts_in_gpa' => true,
                    'sort_order' => $cs->sort_order ?: $i,
                ]);
                $added++;
            }

            return $added;
        });
    }

    /**
     * Save the papers of one class. $rows: [exam_subject_id => [exam_date, starts_at, ends_at, pass_marks, is_optional,
     * counts_in_gpa, components => [theory => [full, pass], practical => …, ca => …]]].
     */
    public function updateClass(Examination $exam, int $classId, array $rows): void
    {
        $papers = $exam->subjects()->with('subject:id,name')->where('school_class_id', $classId)->get()->keyBy('id');
        $errors = [];
        $clean = [];

        foreach ($rows as $id => $row) {
            $paper = $papers->get((int) $id);
            if (! $paper) {
                continue;
            }
            $components = [];
            foreach (ExaminationSubject::COMPONENTS as $name) {
                $full = (float) ($row['components'][$name]['full'] ?? 0);
                $pass = (float) ($row['components'][$name]['pass'] ?? 0);
                if ($full <= 0) {
                    continue;
                }
                if ($pass > $full) {
                    $errors["rows.$id.components.$name.pass"] = __(':subject: pass marks cannot exceed full marks.', ['subject' => $paper->subject?->name]);
                }
                $components[$name] = ['full' => $full, 'pass' => $pass];
            }
            $full = array_sum(array_column($components, 'full'));
            if ($full <= 0) {
                $errors["rows.$id.components"] = __(':subject: at least one component needs full marks.', ['subject' => $paper->subject?->name]);
            }
            $passMarks = (float) ($row['pass_marks'] ?? 0);
            if ($passMarks > $full) {
                $errors["rows.$id.pass_marks"] = __(':subject: the overall pass mark cannot exceed the total.', ['subject' => $paper->subject?->name]);
            }
            $date = ! empty($row['exam_date']) ? Carbon::parse($row['exam_date'])->toDateString() : null;
            if ($date && $exam->starts_on && $exam->ends_on && ($date < $exam->starts_on->toDateString() || $date > $exam->ends_on->toDateString())) {
                $errors["rows.$id.exam_date"] = __(':subject: the date is outside the exam period.', ['subject' => $paper->subject?->name]);
            }

            $clean[$paper->id] = [
                'exam_date' => $date,
                'starts_at' => $row['starts_at'] ?? null ?: null,
                'ends_at' => $row['ends_at'] ?? null ?: null,
                'components' => $components,
                'full_marks' => $full,
                'pass_marks' => $passMarks,
                'is_optional' => ! empty($row['is_optional']),
                'counts_in_gpa' => ! empty($row['counts_in_gpa']),
            ];
        }

        // Two papers of the same class (and group) cannot overlap in time on the same day.
        $list = array_map(fn ($id) => $clean[$id] + ['id' => $id, 'group' => $papers[$id]->study_group_id, 'name' => $papers[$id]->subject?->name], array_keys($clean));
        foreach ($list as $i => $a) {
            foreach (array_slice($list, $i + 1) as $b) {
                $sameAudience = $a['group'] === null || $b['group'] === null || $a['group'] === $b['group'];
                if ($a['exam_date'] && $a['exam_date'] === $b['exam_date'] && $sameAudience && $a['starts_at'] && $b['starts_at']
                    && $a['starts_at'] < ($b['ends_at'] ?: $b['starts_at']) && $b['starts_at'] < ($a['ends_at'] ?: $a['starts_at'])) {
                    $errors["rows.{$b['id']}.starts_at"] = __(':a and :b overlap on :date.', ['a' => $a['name'], 'b' => $b['name'], 'date' => $a['exam_date']]);
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($papers, $clean) {
            foreach ($clean as $id => $attributes) {
                $papers[$id]->update($attributes);
            }
        });
    }

    public function changeStatus(Examination $exam, string $status): Examination
    {
        if (! in_array($status, self::MANUAL_TRANSITIONS[$exam->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('The exam cannot move from :from to :to here.', ['from' => $exam->status, 'to' => $status])]);
        }
        if ($status === 'scheduled' && ! $exam->subjects()->exists()) {
            throw ValidationException::withMessages(['status' => __('Add at least one class and its subjects before scheduling the exam.')]);
        }

        $exam->update(['status' => $status]);

        return $exam;
    }
}
