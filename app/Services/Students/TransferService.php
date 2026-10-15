<?php

namespace App\Services\Students;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\Students\Student;
use App\Models\Students\StudentTransfer;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Branch → branch transfer inside one institution (master prompt §8).
 * The source branch requests; the target branch accepts (a new student record is admitted there with the
 * same profile and guardians, and the source record becomes "transferred") or rejects.
 * Each branch keeps its own record and history; nothing is moved between tenants silently.
 */
class TransferService
{
    public function __construct(
        protected TenantContext $tenant,
        protected StudentService $students,
    ) {}

    public function request(Student $student, int $toBranchId, string $reason, User $actor): StudentTransfer
    {
        $from = $this->tenant->branch();
        $to = Branch::query()->find($toBranchId);

        $errors = [];
        if (! $student->isActive()) {
            $errors['student'] = __('Only active students can be transferred.');
        }
        if (! $to || $to->institution_id !== $from->institution_id || $to->id === $from->id) {
            $errors['to_branch_id'] = __('Choose another branch of this institution.');
        } elseif (! $to->isActive()) {
            $errors['to_branch_id'] = __('The target branch is not active.');
        }
        if (StudentTransfer::query()->where('student_id', $student->id)->where('status', 'pending')->exists()) {
            $errors['student'] = __('A transfer request for this student is already pending.');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return StudentTransfer::query()->create([
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'student_id' => $student->id,
            'requested_on' => now()->toDateString(),
            'reason' => $reason,
            'requested_by' => $actor->id,
        ]);
    }

    /** Called in the target branch. $enrollment = academic_session_id, school_class_id, section_id, study_group_id?, roll_no?. */
    public function accept(StudentTransfer $transfer, array $enrollment, User $actor): Student
    {
        $target = $this->tenant->branch();
        if ($transfer->to_branch_id !== $target->id) {
            abort(403);
        }

        return DB::transaction(function () use ($transfer, $enrollment, $actor, $target) {
            $transfer = StudentTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if (! $transfer->isPending()) {
                throw ValidationException::withMessages(['status' => __('This transfer has already been decided.')]);
            }

            /** @var Student $source */
            $source = Student::query()->withoutGlobalScope(BranchScope::class)
                ->with(['guardians' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)])
                ->where('institution_id', $transfer->institution_id)->findOrFail($transfer->student_id);
            $fromBranch = Branch::query()->findOrFail($transfer->from_branch_id);

            $guardians = $source->guardians->map(fn ($g) => [
                'name' => $g->name, 'name_bn' => $g->name_bn, 'phone' => $g->phone, 'alt_phone' => $g->alt_phone, 'email' => $g->email,
                'occupation' => $g->occupation, 'address' => $g->address, 'relation' => $g->pivot->relation,
                'is_primary' => (bool) $g->pivot->is_primary, 'is_emergency_contact' => (bool) $g->pivot->is_emergency_contact,
            ])->all();

            $profile = $source->only([
                'name', 'name_bn', 'gender', 'birth_registration_no', 'blood_group', 'religion', 'nationality', 'phone', 'email',
                'present_address', 'permanent_address', 'emergency_contact_name', 'emergency_contact_phone',
            ]);
            $profile['date_of_birth'] = $source->date_of_birth->toDateString();
            $profile['previous_school'] = $fromBranch->name.' ('.$source->student_code.')';
            $profile['admission_date'] = now()->toDateString();
            $profile['medical_notes'] = $source->medical_notes;

            $new = $this->students->admit($profile, $guardians, $enrollment);

            if ($source->photo_path && Storage::disk('local')->exists($source->photo_path)) {
                $path = $target->storagePath('students/'.$new->id).'/'.basename($source->photo_path);
                Storage::disk('local')->copy($source->photo_path, $path);
                $new->forceFill(['photo_path' => $path])->save();
            }

            // Close the record in the source branch inside that branch's own tenant context.
            $this->tenant->runForBranch($fromBranch, fn () => $this->students->changeStatus(
                Student::query()->findOrFail($source->id), 'transferred', now()->toDateString()));

            $transfer->forceFill([
                'status' => 'accepted',
                'new_student_id' => $new->id,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            return $new;
        });
    }

    public function reject(StudentTransfer $transfer, string $note, User $actor): StudentTransfer
    {
        if ($transfer->to_branch_id !== $this->tenant->branchId()) {
            abort(403);
        }

        return $this->close($transfer, 'rejected', $note, $actor);
    }

    public function cancel(StudentTransfer $transfer, User $actor): StudentTransfer
    {
        if ($transfer->from_branch_id !== $this->tenant->branchId()) {
            abort(403);
        }

        return $this->close($transfer, 'cancelled', null, $actor);
    }

    protected function close(StudentTransfer $transfer, string $status, ?string $note, User $actor): StudentTransfer
    {
        if (! $transfer->isPending()) {
            throw ValidationException::withMessages(['status' => __('This transfer has already been decided.')]);
        }

        $transfer->forceFill(['status' => $status, 'decision_note' => $note, 'decided_by' => $actor->id, 'decided_at' => now()])->save();

        return $transfer;
    }
}
