<?php

namespace App\Services\Exams;

use App\Models\Academic\Section;
use App\Models\Academic\TimetableEntry;
use App\Models\Exams\ExamMark;
use App\Models\Exams\Examination;
use App\Models\Exams\ExaminationSubject;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks entry per paper & section. Exam controllers (examinations.update) may enter every paper; teachers
 * (marks.enter) only the subject & section pairs they teach in the exam session's class routine.
 * Marks are open while the exam is "scheduled" or "marks_entry"; afterwards changes go through MarkCorrectionService.
 */
class MarkEntryService
{
    /** Enrollment outcomes that no longer sit exams (unless marks already exist). */
    public const LEFT = ['transferred', 'withdrawn'];

    public function canEnter(User $user, ExaminationSubject $paper, Section $section): bool
    {
        if ((int) $section->school_class_id !== (int) $paper->school_class_id || ! $user->can('marks.enter')) {
            return false;
        }
        if ($user->can('examinations.update')) {
            return true;
        }

        return $this->teachingPairs($user, $paper->examination)->contains($section->id.'-'.$paper->subject_id);
    }

    /** "section_id-subject_id" keys the user teaches in the exam session's routine. */
    public function teachingPairs(User $user, Examination $exam): Collection
    {
        return TimetableEntry::query()->where('academic_session_id', $exam->academic_session_id)->where('teacher_id', $user->id)
            ->whereNotNull('subject_id')->get(['section_id', 'subject_id'])
            ->map(fn ($e) => $e->section_id.'-'.$e->subject_id)->unique()->values();
    }

    /** Students of the section who sit the paper, by roll. @return Collection<int,StudentEnrollment> */
    public function candidates(ExaminationSubject $paper, Section $section): Collection
    {
        $exam = $paper->examination;
        $withMarks = ExamMark::query()->where('examination_subject_id', $paper->id)->pluck('student_id');

        return StudentEnrollment::query()->with('student:id,name,student_code')
            ->where('academic_session_id', $exam->academic_session_id)
            ->where('school_class_id', $paper->school_class_id)
            ->where('section_id', $section->id)
            ->when($paper->study_group_id, fn ($q, $g) => $q->where('study_group_id', $g))
            ->where(fn ($q) => $q->whereNotIn('status', self::LEFT)->orWhereIn('student_id', $withMarks))
            ->orderBy('roll_no')->get();
    }

    /**
     * @param  array<int|string,array{theory?:mixed,practical?:mixed,ca?:mixed,absent?:mixed}>  $entries  student_id => values
     * @return array{saved:int, cleared:int}
     */
    public function save(ExaminationSubject $paper, Section $section, array $entries, User $user): array
    {
        $exam = $paper->examination;
        if (! $exam->acceptsMarks()) {
            throw ValidationException::withMessages(['entries' => __('Marks entry is closed for this exam. Request a correction instead.')]);
        }

        $components = $paper->activeComponents();
        $candidates = $this->candidates($paper, $section)->keyBy('student_id');
        $rows = [];
        $errors = [];

        foreach ($entries as $studentId => $entry) {
            $studentId = (int) $studentId;
            if (! $candidates->has($studentId) || ! is_array($entry)) {
                continue;
            }
            $absent = ! empty($entry['absent']);
            [$marks, $err] = $this->normalise($components, $entry, $absent, (string) $candidates[$studentId]->student?->name, "entries.$studentId");
            $errors += $err;
            $rows[$studentId] = [$marks, $absent];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($rows, $paper, $section, $user) {
            $existing = ExamMark::query()->where('examination_subject_id', $paper->id)->whereIn('student_id', array_keys($rows))
                ->lockForUpdate()->get()->keyBy('student_id');
            $saved = 0;
            $cleared = 0;

            foreach ($rows as $studentId => [$marks, $absent]) {
                $mark = $existing->get($studentId);
                if (! $absent && array_filter($marks, fn ($v) => $v !== null) === []) {
                    if ($mark) {
                        $mark->delete();
                        $cleared++;
                    }

                    continue;
                }

                $mark ??= new ExamMark(['examination_id' => $paper->examination_id, 'examination_subject_id' => $paper->id, 'student_id' => $studentId]);
                $mark->fill(['section_id' => $section->id, 'marks' => $absent ? null : $marks, 'is_absent' => $absent, 'total' => $this->total($marks, $absent)]);
                if (! $mark->exists || $mark->isDirty()) {
                    $mark->forceFill(['entered_by' => $user->id])->save();
                    $saved++;
                }
            }

            if ($paper->examination->status === 'scheduled') {
                $paper->examination->update(['status' => 'marks_entry']); // first marks move the exam into marks entry
            }

            return ['saved' => $saved, 'cleared' => $cleared];
        });
    }

    /**
     * Validate one student's component marks. Blank = not entered yet (null).
     *
     * @return array{0:array<string,?float>, 1:array<string,string>}
     */
    public function normalise(array $components, array $entry, bool $absent, string $label, string $errorPrefix): array
    {
        $marks = [];
        $errors = [];
        foreach ($components as $name => $component) {
            $raw = $entry[$name] ?? null;
            if ($absent || $raw === null || trim((string) $raw) === '') {
                $marks[$name] = null;

                continue;
            }
            if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > (float) $component['full']) {
                $errors["$errorPrefix.$name"] = __(':name: :component marks must be between 0 and :full.', [
                    'name' => $label, 'component' => __(ucfirst($name === 'ca' ? 'CA' : $name)), 'full' => (float) $component['full'],
                ]);

                continue;
            }
            $marks[$name] = round((float) $raw, 2);
        }

        return [$marks, $errors];
    }

    public function total(?array $marks, bool $absent): ?float
    {
        if ($absent) {
            return 0.0;
        }
        $marks ??= [];
        if ($marks === [] || in_array(null, $marks, true)) {
            return null;
        }

        return round(array_sum($marks), 2);
    }

    /**
     * Entry progress of every paper & section of the exam.
     *
     * @return Collection<string,array{entered:int, expected:int}> keyed "paper_id-section_id"
     */
    public function progress(Examination $exam): Collection
    {
        $entered = ExamMark::query()->where('examination_id', $exam->id)
            ->selectRaw('examination_subject_id, section_id, COUNT(*) as n')->groupBy('examination_subject_id', 'section_id')->toBase()->get()
            ->mapWithKeys(fn ($r) => [$r->examination_subject_id.'-'.$r->section_id => (int) $r->n]);

        $enrolled = StudentEnrollment::query()->where('academic_session_id', $exam->academic_session_id)->whereNotIn('status', self::LEFT)
            ->selectRaw('school_class_id, section_id, study_group_id, COUNT(*) as n')->groupBy('school_class_id', 'section_id', 'study_group_id')
            ->toBase()->get();

        $out = collect();
        foreach ($exam->subjects()->get() as $paper) {
            foreach ($enrolled->where('school_class_id', $paper->school_class_id)->groupBy('section_id') as $sectionId => $groups) {
                $expected = (int) $groups->filter(fn ($g) => ! $paper->study_group_id || (int) $g->study_group_id === (int) $paper->study_group_id)->sum('n');
                $out[$paper->id.'-'.$sectionId] = ['entered' => $entered[$paper->id.'-'.$sectionId] ?? 0, 'expected' => $expected];
            }
        }

        return $out;
    }
}
