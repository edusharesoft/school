<?php

namespace App\Services\Exams;

use App\Models\Academic\Section;
use App\Models\Exams\ExamMark;
use App\Models\Exams\ExaminationSubject;
use App\Models\Exams\MarkCorrection;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mark changes after marks entry closed (exam processing / approved / published).
 * Request (teacher of the paper or exam controller) → approve / reject by marks.approve-correction.
 * A reviewer never approves their own request, except that approvers' own requests are applied
 * immediately (recorded as approved by themselves in the audit log). Every approval re-processes the
 * exam's results, which sends an approved / published exam back to "processing" for re-approval.
 */
class MarkCorrectionService
{
    public function __construct(protected MarkEntryService $entries, protected ResultService $results) {}

    public function request(ExaminationSubject $paper, int $studentId, array $entry, string $reason, User $user): MarkCorrection
    {
        $exam = $paper->examination;
        if (! $exam->needsCorrectionRequest()) {
            throw ValidationException::withMessages(['reason' => __('Marks entry is still open — change the marks directly.')]);
        }

        $enrollment = StudentEnrollment::query()->where('academic_session_id', $exam->academic_session_id)
            ->where('school_class_id', $paper->school_class_id)->where('student_id', $studentId)->with('student:id,name')->first();
        $section = $enrollment?->section_id ? Section::query()->find($enrollment->section_id) : null;
        if (! $enrollment || ! $section || ($paper->study_group_id && (int) $paper->study_group_id !== (int) $enrollment->study_group_id)) {
            throw ValidationException::withMessages(['student_id' => __('This student does not sit this paper.')]);
        }
        if (! $this->entries->canEnter($user, $paper, $section)) {
            abort(403, __('You may only request corrections for papers you teach.'));
        }

        $absent = ! empty($entry['absent']);
        [$marks, $errors] = $this->entries->normalise($paper->activeComponents(), $entry, $absent, (string) $enrollment->student?->name, 'marks');
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! $absent && in_array(null, $marks, true)) {
            throw ValidationException::withMessages(['marks' => __('Enter every component or mark the student absent.')]);
        }

        return DB::transaction(function () use ($paper, $studentId, $section, $marks, $absent, $reason, $user) {
            $mark = ExamMark::query()->firstOrCreate(
                ['examination_subject_id' => $paper->id, 'student_id' => $studentId],
                ['examination_id' => $paper->examination_id, 'section_id' => $section->id, 'marks' => null, 'is_absent' => false, 'total' => null],
            );
            if ($mark->corrections()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['reason' => __('A correction for these marks is already pending.')]);
            }
            if ((bool) $mark->is_absent === $absent && ($absent || $mark->marks == $marks)) {
                throw ValidationException::withMessages(['marks' => __('The new marks are the same as the current marks.')]);
            }

            $correction = new MarkCorrection([
                'exam_mark_id' => $mark->id,
                'old_marks' => $mark->marks,
                'old_absent' => $mark->is_absent,
                'new_marks' => $absent ? null : $marks,
                'new_absent' => $absent,
                'reason' => mb_substr(trim($reason), 0, 500),
            ]);
            $correction->forceFill(['status' => 'pending', 'requested_by' => $user->id])->save();

            if ($user->can('marks.approve-correction')) {
                $this->apply($correction, $user, __('Entered by an approver'));
            }

            return $correction->fresh();
        });
    }

    public function approve(MarkCorrection $correction, User $reviewer, ?string $note = null): void
    {
        $this->assertReviewable($correction, $reviewer);
        DB::transaction(fn () => $this->apply($correction, $reviewer, $note));
    }

    public function reject(MarkCorrection $correction, User $reviewer, string $note): void
    {
        $this->assertReviewable($correction, $reviewer);
        $correction->forceFill(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => mb_substr($note, 0, 500)])->save();
    }

    protected function apply(MarkCorrection $correction, User $reviewer, ?string $note): void
    {
        $mark = $correction->mark()->lockForUpdate()->firstOrFail();
        $mark->fill([
            'marks' => $correction->new_absent ? null : $correction->new_marks,
            'is_absent' => $correction->new_absent,
            'total' => $this->entries->total($correction->new_marks, $correction->new_absent),
        ])->forceFill(['entered_by' => $correction->requested_by])->save();

        $correction->forceFill(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note ? mb_substr($note, 0, 500) : null])->save();

        $this->results->process($mark->examination()->firstOrFail(), $reviewer);
    }

    protected function assertReviewable(MarkCorrection $correction, User $reviewer): void
    {
        if (! $correction->isPending()) {
            throw ValidationException::withMessages(['status' => __('This request has already been reviewed.')]);
        }
        if (! $reviewer->can('marks.approve-correction')) {
            abort(403);
        }
        if ((int) $correction->requested_by === (int) $reviewer->id) {
            throw ValidationException::withMessages(['status' => __('You cannot review your own request.')]);
        }
    }
}
