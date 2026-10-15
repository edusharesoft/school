<?php

namespace App\Services\Learning;

use App\Models\Academic\AcademicSession;
use App\Models\Learning\Assignment;
use App\Models\Learning\AssignmentSubmission;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Homework / assignments / projects: drafting, publishing (notifies students and guardians once), hand-in with late
 * detection, evaluation with marks and feedback, "return for revision", closing. Files live on the private disk.
 */
class AssignmentService
{
    public function __construct(protected LearningAccess $access, protected NotificationHub $hub, protected RecipientResolver $recipients) {}

    public function save(array $data, ?UploadedFile $file, User $user, ?Assignment $assignment = null): Assignment
    {
        $classId = (int) $data['school_class_id'];
        $sectionId = filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null;
        $subjectId = filled($data['subject_id'] ?? null) ? (int) $data['subject_id'] : null;

        if (! $this->access->canTeach($user, $classId, $sectionId)) {
            throw ValidationException::withMessages(['section_id' => __('You can only give work to the sections you teach.')]);
        }
        if (! $this->access->subjectBelongsToClass($classId, $subjectId)) {
            throw ValidationException::withMessages(['subject_id' => __('This subject is not taught in the chosen class.')]);
        }
        if ($assignment && ! $this->access->manages($user, $assignment)) {
            abort(403);
        }
        if ($assignment && $assignment->status === 'closed') {
            throw ValidationException::withMessages(['status' => __('A closed assignment cannot be edited. Reopen it first.')]);
        }
        if ($assignment && $assignment->status === 'published' && $assignment->submissions()->exists()
            && ($classId !== $assignment->school_class_id || $sectionId !== $assignment->section_id)) {
            throw ValidationException::withMessages(['school_class_id' => __('The class cannot be changed after students have handed in work.')]);
        }

        $session = $assignment?->session ?? AcademicSession::query()->current()->first();
        if (! $session) {
            throw ValidationException::withMessages(['school_class_id' => __('There is no current academic session. Set one under Academics first.')]);
        }

        $maxMarks = filled($data['max_marks'] ?? null) ? (float) $data['max_marks'] : null;
        if ($assignment && $maxMarks !== null && $assignment->submissions()->where('marks', '>', $maxMarks)->exists()) {
            throw ValidationException::withMessages(['max_marks' => __('Some students already have more marks than this maximum.')]);
        }

        return DB::transaction(function () use ($data, $file, $user, $assignment, $session, $maxMarks, $sectionId, $subjectId, $classId) {
            $assignment ??= new Assignment;
            $assignment->fill(collect($data)->only($assignment->getFillable())->all());
            $assignment->school_class_id = $classId;
            $assignment->section_id = $sectionId;
            $assignment->subject_id = $subjectId;
            $assignment->max_marks = $maxMarks;
            $assignment->allow_late = (bool) ($data['allow_late'] ?? false);
            if (! $assignment->exists) {
                $assignment->forceFill(['academic_session_id' => $session->id, 'status' => 'draft', 'created_by' => $user->id]);
            }
            if ($file) {
                if ($assignment->attachment_path) {
                    Storage::disk('local')->delete($assignment->attachment_path);
                }
                $assignment->attachment_path = $file->store(tenant()->branch()->storagePath('assignments'), 'local');
                $assignment->attachment_name = mb_substr($file->getClientOriginalName(), 0, 191);
            } elseif (! empty($data['remove_attachment']) && $assignment->attachment_path) {
                Storage::disk('local')->delete($assignment->attachment_path);
                $assignment->attachment_path = $assignment->attachment_name = null;
            }
            $assignment->save();

            return $assignment;
        });
    }

    public function publish(Assignment $assignment, User $user): Assignment
    {
        if ($assignment->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Only a draft can be published.')]);
        }
        if ($assignment->due_at->isPast() && ! $assignment->allow_late) {
            throw ValidationException::withMessages(['due_at' => __('The due date has already passed. Change it, or allow late hand-in.')]);
        }
        $assignment->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $this->announce($assignment, $user);

        return $assignment;
    }

    public function close(Assignment $assignment): Assignment
    {
        if ($assignment->status === 'published') {
            $assignment->forceFill(['status' => 'closed'])->save();
        }

        return $assignment;
    }

    public function reopen(Assignment $assignment): Assignment
    {
        if ($assignment->status === 'closed') {
            $assignment->forceFill(['status' => 'published'])->save();
        }

        return $assignment;
    }

    /** Hand in (or hand in again after "returned"). */
    public function submit(Assignment $assignment, Student $student, ?string $text, ?UploadedFile $file): AssignmentSubmission
    {
        $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $student->id)->first();

        if (! $assignment->acceptsWorkFrom($submission)) {
            throw ValidationException::withMessages(['text' => match (true) {
                $submission?->status === 'evaluated' => __('This work has already been evaluated.'),
                $assignment->status !== 'published' => __('This assignment is not open for hand-in.'),
                $assignment->submission_mode === 'none' => __('This work is not handed in online.'),
                default => __('The due date has passed and late hand-in is not allowed.'),
            }]);
        }

        $text = trim((string) $text) ?: null;
        $hasFile = $file !== null || ($submission?->file_path !== null);
        $needsFile = in_array($assignment->submission_mode, ['file'], true);
        $needsText = in_array($assignment->submission_mode, ['text'], true);
        if (($needsFile && ! $hasFile) || ($needsText && ! $text) || ($assignment->submission_mode === 'either' && ! $text && ! $hasFile)) {
            throw ValidationException::withMessages(['text' => $needsFile ? __('Please attach your file.') : ($needsText ? __('Please write your answer.') : __('Please write your answer or attach a file.'))]);
        }
        if ($assignment->submission_mode === 'file' && $text) {
            $text = null;
        }
        if ($assignment->submission_mode === 'text') {
            $file = null;
        }

        return DB::transaction(function () use ($assignment, $student, $submission, $text, $file) {
            $submission ??= new AssignmentSubmission(['text' => null]);
            if (! $submission->exists) {
                $submission->forceFill(['assignment_id' => $assignment->id, 'student_id' => $student->id]);
            }
            if ($file) {
                if ($submission->file_path) {
                    Storage::disk('local')->delete($submission->file_path);
                }
                $submission->file_path = $file->store(tenant()->branch()->storagePath('submissions/'.$assignment->id), 'local');
                $submission->file_name = mb_substr($file->getClientOriginalName(), 0, 191);
            }
            $submission->text = $text;
            $submission->forceFill([
                'submitted_at' => now(), 'is_late' => $assignment->isPastDue(), 'status' => 'submitted',
                'attempts' => $submission->attempts + 1, 'marks' => null, 'evaluated_by' => null, 'evaluated_at' => null,
            ]);
            $submission->save();

            return $submission;
        });
    }

    /**
     * Save the evaluation grid. $rows: student_id => [marks?, feedback?, action?] where action is
     * "evaluate" (default when marks or feedback is given), "return" (ask for a new version) or "clear".
     *
     * @return array{evaluated:int, returned:int}
     */
    public function evaluate(Assignment $assignment, array $rows, User $user): array
    {
        if (! $this->access->manages($user, $assignment)) {
            abort(403);
        }
        if ($assignment->status === 'draft') {
            throw ValidationException::withMessages(['rows' => __('Publish the assignment first.')]);
        }

        $audience = $assignment->audience()->pluck('id')->flip();
        $existing = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->get()->keyBy('student_id');
        $stats = ['evaluated' => 0, 'returned' => 0];
        $notify = [];

        DB::transaction(function () use ($assignment, $rows, $user, $audience, $existing, &$stats, &$notify) {
            foreach ($rows as $studentId => $row) {
                $studentId = (int) $studentId;
                if (! $audience->has($studentId)) {
                    continue;
                }
                $marks = filled($row['marks'] ?? null) ? round((float) $row['marks'], 2) : null;
                $feedback = trim((string) ($row['feedback'] ?? '')) ?: null;
                /** @var AssignmentSubmission|null $current */
                $current = $existing->get($studentId);
                $action = $row['action'] ?? 'skip';
                // Typed marks / feedback are never lost: a "no change" row that differs from what is stored is saved.
                if ($action === 'skip' && ($marks !== null || $feedback !== null)
                    && ($marks !== ($current?->marks !== null ? (float) $current->marks : null) || $feedback !== $current?->feedback)) {
                    $action = 'evaluate';
                }
                if ($action === 'skip') {
                    continue;
                }
                if ($marks !== null) {
                    if (! $assignment->isGraded()) {
                        throw ValidationException::withMessages(["rows.$studentId.marks" => __('This assignment is not graded.')]);
                    }
                    if ($marks < 0 || $marks > (float) $assignment->max_marks) {
                        throw ValidationException::withMessages(["rows.$studentId.marks" => __('Marks must be between 0 and :max.', ['max' => $assignment->max_marks + 0])]);
                    }
                }

                /** @var AssignmentSubmission|null $submission */
                $submission = $existing->get($studentId);
                if ($action === 'return' && ! $submission?->submitted_at) {
                    throw ValidationException::withMessages(["rows.$studentId.action" => __('Nothing has been handed in to return.')]);
                }
                if (! $submission) {
                    $submission = new AssignmentSubmission(['text' => null]);
                    $submission->forceFill(['assignment_id' => $assignment->id, 'student_id' => $studentId, 'status' => 'submitted', 'is_late' => false]);
                }
                $wasEvaluated = $submission->status === 'evaluated';
                $submission->forceFill([
                    'marks' => $action === 'return' ? null : $marks,
                    'feedback' => $feedback,
                    'status' => $action === 'return' ? 'returned' : 'evaluated',
                    'evaluated_by' => $user->id,
                    'evaluated_at' => now(),
                ])->save();

                $action === 'return' ? $stats['returned']++ : $stats['evaluated']++;
                if (! $wasEvaluated || $action === 'return') {
                    $notify[] = [$studentId, $submission];
                }
            }
        });

        foreach ($notify as [$studentId, $submission]) {
            $this->announceResult($assignment, Student::query()->find($studentId), $submission, $user);
        }

        return $stats;
    }

    /** Students of the audience with their submission (or null). @return \Illuminate\Support\Collection<int,array{student:Student,submission:?AssignmentSubmission}> */
    public function roster(Assignment $assignment)
    {
        $subs = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->get()->keyBy('student_id');
        $students = $assignment->audience()->with('currentEnrollment')->orderBy('name')->get();

        return $students->map(fn (Student $s) => ['student' => $s, 'submission' => $subs->get($s->id)])
            ->sortBy(fn ($r) => sprintf('%05d-%s', $r['student']->currentEnrollment?->roll_no ?? 99999, $r['student']->name))->values();
    }

    public function deleteFiles(Assignment $assignment): void
    {
        if ($assignment->attachment_path) {
            Storage::disk('local')->delete($assignment->attachment_path);
        }
        foreach ($assignment->submissions()->whereNotNull('file_path')->pluck('file_path') as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    protected function announce(Assignment $assignment, User $user): void
    {
        $assignment->loadMissing(['subject:id,name,name_bn', 'schoolClass:id,name', 'section:id,name']);
        $students = $assignment->audience()->with(['user:id,locale'])->get();
        $vars = [
            'title' => $assignment->title, 'kind' => $assignment->kindLabel(), 'subject' => $assignment->subject?->displayName() ?? '—',
            'class' => $assignment->schoolClass?->name, 'due' => $assignment->due_at->format('d M Y H:i'),
        ];
        $recipients = collect();
        foreach ($students as $student) {
            $recipients->push($this->recipients->student($student, $vars));
        }
        $recipients = $recipients->merge($this->recipients->guardiansOf($students, $vars)->values());

        $this->hub->notify('assignment.published', $recipients, $vars, $assignment, [
            'url' => tenant()->branch()?->url('/student/assignments/'.$assignment->id), 'user' => $user,
        ]);
    }

    protected function announceResult(Assignment $assignment, ?Student $student, AssignmentSubmission $submission, User $user): void
    {
        if (! $student) {
            return;
        }
        $returned = $submission->status === 'returned';
        $vars = [
            'title' => $assignment->title,
            'result' => $returned ? __('Please revise and hand in again.') : ($submission->marks !== null ? ($submission->marks + 0).' / '.($assignment->max_marks + 0) : __('Evaluated')),
            'feedback' => \Illuminate\Support\Str::limit((string) $submission->feedback, 120),
        ];
        $recipients = collect([$this->recipients->student($student, $vars)])->merge($this->recipients->guardiansOf($student, $vars)->values());
        $this->hub->notify('assignment.evaluated', $recipients, $vars, $submission, [
            'url' => tenant()->branch()?->url('/student/assignments/'.$assignment->id), 'user' => $user,
            'dedupe_suffix' => (string) $submission->attempts,
        ]);
    }
}
