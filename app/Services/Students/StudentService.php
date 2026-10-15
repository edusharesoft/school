<?php

namespace App\Services\Students;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\StudyGroup;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student lifecycle: admission (record + guardians + first enrollment), enrollment,
 * promotion and status changes. Every operation runs in one DB transaction.
 */
class StudentService
{
    public function __construct(
        protected TenantContext $tenant,
        protected SequenceService $sequences,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array  $student      Student attributes (fillable)
     * @param  array  $guardians    [['name','phone','relation','is_primary'?,'is_emergency_contact'?, …], …]
     * @param  array|null  $enrollment  ['academic_session_id','school_class_id','section_id','study_group_id'?,'roll_no'?]
     */
    public function admit(array $student, array $guardians = [], ?array $enrollment = null): Student
    {
        if (($student['status'] ?? 'active') === 'active') {
            app(\App\Services\Saas\SubscriptionService::class)->assertCanAddStudents($this->tenant->institutionId());
        }

        return DB::transaction(function () use ($student, $guardians, $enrollment) {
            $model = new Student(Arr::except($student, ['student_code', 'user_id']));
            $model->admission_date ??= now()->toDateString();
            $model->status ??= 'active';
            $model->student_code = $this->nextStudentCode(Carbon::parse($model->admission_date));
            $model->save();

            $this->syncGuardians($model, $guardians);

            if ($enrollment) {
                $this->enroll($model, $enrollment);
            }

            return $model->fresh(['guardians', 'enrollments']);
        });
    }

    /** Branch code + 2-digit admission year + 4-digit running number, e.g. BR01-260042. */
    public function nextStudentCode(Carbon $admissionDate): string
    {
        $branch = $this->tenant->branch();
        $year = $admissionDate->format('Y');
        $number = $this->sequences->next('student_code', $year);

        return sprintf('%s-%s%04d', strtoupper($branch->code), substr($year, -2), $number);
    }

    /**
     * Guardians are matched by phone inside the branch so siblings share one guardian record
     * (and one parent-portal account).
     */
    public function syncGuardians(Student $student, array $guardians): void
    {
        $attach = [];
        $hasPrimary = collect($guardians)->contains(fn ($g) => ! empty($g['is_primary']));

        foreach (array_values($guardians) as $i => $data) {
            if (empty($data['name']) || empty($data['phone'])) {
                continue;
            }

            $phone = preg_replace('/[\s-]/', '', (string) $data['phone']);
            $guardian = Guardian::query()->where('phone', $phone)->first()
                ?? new Guardian;
            $guardian->fill(Arr::only($data, ['name', 'name_bn', 'alt_phone', 'email', 'occupation', 'nid_number', 'address']));
            $guardian->phone = $phone;
            $guardian->save();

            $attach[$guardian->id] = [
                'relation' => $data['relation'] ?? 'other',
                'is_primary' => $hasPrimary ? ! empty($data['is_primary']) : $i === 0,
                'is_emergency_contact' => ! empty($data['is_emergency_contact']),
            ];
        }

        if ($attach) {
            $student->guardians()->syncWithoutDetaching($attach);
        }
    }

    /**
     * Enroll a student in a session. Enforces: one enrollment per student per session, section belongs
     * to class, group required for group classes, section capacity, unique roll per section & session.
     */
    public function enroll(Student $student, array $data, ?StudentEnrollment $previous = null, bool $ignoreCapacity = false): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $data, $previous, $ignoreCapacity) {
            /** @var AcademicSession $session */
            $session = AcademicSession::query()->findOrFail($data['academic_session_id']);
            /** @var SchoolClass $class */
            $class = SchoolClass::query()->findOrFail($data['school_class_id']);
            /** @var Section $section */
            $section = Section::query()->lockForUpdate()->findOrFail($data['section_id']);
            $groupId = $data['study_group_id'] ?? null;

            $errors = [];
            if (! $session->isOpen()) {
                $errors['academic_session_id'] = __('This academic session is closed.');
            }
            if ($section->school_class_id !== $class->id) {
                $errors['section_id'] = __('The section does not belong to the selected class.');
            }
            if ($class->has_groups && ! $groupId) {
                $errors['study_group_id'] = __('Select a group for this class.');
            }
            if ($groupId && ! StudyGroup::query()->whereKey($groupId)->exists()) {
                $errors['study_group_id'] = __('Unknown group.');
            }
            if (StudentEnrollment::query()->where('student_id', $student->id)->where('academic_session_id', $session->id)->exists()) {
                $errors['academic_session_id'] = __('This student is already enrolled in the :session session.', ['session' => $session->name]);
            }
            if (! $ignoreCapacity && $section->activeCount($session->id) >= $section->capacity) {
                $errors['section_id'] = __('Section :name is full (capacity :capacity).', ['name' => $section->name, 'capacity' => $section->capacity]);
            }

            $roll = ! empty($data['roll_no']) ? (int) $data['roll_no'] : null;
            if ($roll !== null && $this->rollTaken($section->id, $session->id, $roll)) {
                $errors['roll_no'] = __('Roll :roll is already taken in this section.', ['roll' => $roll]);
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $enrollment = new StudentEnrollment([
                'student_id' => $student->id,
                'academic_session_id' => $session->id,
                'school_class_id' => $class->id,
                'section_id' => $section->id,
                'study_group_id' => $groupId,
                'roll_no' => $roll ?? $this->nextRoll($section->id, $session->id),
                'status' => 'enrolled',
                'enrolled_on' => $data['enrolled_on'] ?? now()->toDateString(),
                'previous_enrollment_id' => $previous?->id,
            ]);
            $enrollment->save();

            return $enrollment;
        });
    }

    public function rollTaken(int $sectionId, int $sessionId, int $roll, ?int $exceptEnrollmentId = null): bool
    {
        return StudentEnrollment::query()
            ->where('section_id', $sectionId)->where('academic_session_id', $sessionId)->where('roll_no', $roll)
            ->when($exceptEnrollmentId, fn ($q) => $q->whereKeyNot($exceptEnrollmentId))
            ->exists();
    }

    public function nextRoll(int $sectionId, int $sessionId): int
    {
        return (int) StudentEnrollment::query()
            ->where('section_id', $sectionId)->where('academic_session_id', $sessionId)
            ->lockForUpdate()
            ->max('roll_no') + 1;
    }

    /**
     * Promote (or retain) a batch of enrollments into a target session/class.
     * Section is matched by name in the target class (A → A) unless $targetSectionId is given.
     *
     * @param  int[]  $enrollmentIds
     * @return array{promoted:int, skipped:array<int,string>}
     */
    public function promote(array $enrollmentIds, int $targetSessionId, int $targetClassId, ?int $targetSectionId = null, string $outcome = 'promoted'): array
    {
        abort_unless(in_array($outcome, ['promoted', 'repeated'], true), 422);

        return DB::transaction(function () use ($enrollmentIds, $targetSessionId, $targetClassId, $targetSectionId, $outcome) {
            $targetClass = SchoolClass::query()->with('sections')->findOrFail($targetClassId);
            $promoted = 0;
            $skipped = [];

            $enrollments = StudentEnrollment::query()->with(['student', 'section'])->whereIn('id', $enrollmentIds)->where('status', 'enrolled')->get();

            foreach ($enrollments as $old) {
                if ($old->academic_session_id === $targetSessionId) {
                    $skipped[$old->id] = __('Already in the target session.');
                    continue;
                }

                $sectionId = $targetSectionId
                    ?? $targetClass->sections->firstWhere('name', $old->section?->name)?->id
                    ?? $targetClass->sections->first()?->id;

                if (! $sectionId) {
                    $skipped[$old->id] = __('Target class has no sections.');
                    continue;
                }

                try {
                    $this->enroll($old->student, [
                        'academic_session_id' => $targetSessionId,
                        'school_class_id' => $targetClass->id,
                        'section_id' => $sectionId,
                        'study_group_id' => $targetClass->has_groups ? $old->study_group_id : null,
                    ], $old, ignoreCapacity: true);
                } catch (ValidationException $e) {
                    $skipped[$old->id] = collect($e->errors())->flatten()->first();
                    continue;
                }

                $old->update(['status' => $outcome, 'left_on' => now()->toDateString()]);
                $promoted++;
            }

            $this->audit->log('students.promoted', null, [], [
                'target_session_id' => $targetSessionId, 'target_class_id' => $targetClassId,
                'outcome' => $outcome, 'promoted' => $promoted, 'skipped' => count($skipped),
            ]);

            return ['promoted' => $promoted, 'skipped' => $skipped];
        });
    }

    /**
     * Re-admission of a student who left (withdrawn / transferred / suspended): the same permanent record
     * and student ID are kept, the status becomes active again and a new enrollment is created.
     */
    public function readmit(Student $student, array $enrollment, ?string $date = null): StudentEnrollment
    {
        if ($student->isActive()) {
            throw ValidationException::withMessages(['status' => __('The student is already active.')]);
        }
        if ($student->status === 'graduated') {
            throw ValidationException::withMessages(['status' => __('A graduated student cannot be re-admitted; admit them as a new student.')]);
        }

        return DB::transaction(function () use ($student, $enrollment, $date) {
            $date ??= now()->toDateString();

            // Came back within the same session: the closed enrollment (class, section, roll) is reopened.
            $sameSession = $student->enrollments()->where('academic_session_id', $enrollment['academic_session_id'])->first();
            if ($sameSession) {
                $sameSession->update(['status' => 'enrolled', 'left_on' => null]);
                $student->update(['status' => 'active', 'status_changed_on' => $date]);
                $this->audit->log('students.readmitted', $student, [], ['enrollment_id' => $sameSession->id, 'reopened' => true]);

                return $sameSession;
            }

            $previous = $student->enrollments()->first();
            $new = $this->enroll($student, $enrollment + ['enrolled_on' => $date], $previous);
            $student->update(['status' => 'active', 'status_changed_on' => $date]);
            $this->audit->log('students.readmitted', $student, [], ['enrollment_id' => $new->id]);

            return $new;
        });
    }

    /** Withdraw / suspend / reactivate / graduate. Closes the current enrollment when leaving. */
    public function changeStatus(Student $student, string $status, ?string $date = null): Student
    {
        abort_unless(in_array($status, Student::STATUSES, true), 422);

        return DB::transaction(function () use ($student, $status, $date) {
            $date ??= now()->toDateString();
            $student->update(['status' => $status, 'status_changed_on' => $date]);

            if (in_array($status, ['withdrawn', 'transferred', 'graduated'], true)) {
                $student->enrollments()->where('status', 'enrolled')->update([
                    'status' => $status === 'graduated' ? 'completed' : $status,
                    'left_on' => $date,
                    'updated_at' => now(),
                ]);
            }

            return $student;
        });
    }
}
