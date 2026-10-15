<?php

namespace App\Services\Students;

use App\Models\Students\Certificate;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Transfer / character / study certificates with serial numbers and a public verification code.
 * The certificate keeps a snapshot of the facts it states, so later edits to the profile never change
 * an issued certificate. The A4 print view and the PDF (Part 6d) share the same wording.
 */
class CertificateService
{
    public function __construct(
        protected TenantContext $tenant,
        protected SequenceService $sequences,
        protected StudentService $students,
    ) {}

    /**
     * @param  array{conduct?:string, reason?:string, remarks?:string, leaving_date?:string, mark_left?:bool, title?:string, details?:string}  $input
     */
    public function issue(Student $student, string $type, array $input, User $actor): Certificate
    {
        abort_unless(in_array($type, Certificate::TYPES, true), 422);

        $student->loadMissing(['guardians', 'enrollments.session:id,name', 'enrollments.schoolClass:id,name', 'enrollments.section:id,name', 'enrollments.studyGroup:id,name']);
        $enrollment = $student->enrollments->first(); // latest

        if ($type === 'study' && (! $student->isActive() || ! $enrollment || $enrollment->status !== 'enrolled')) {
            throw ValidationException::withMessages(['type' => __('A study certificate is only issued for a currently enrolled student.')]);
        }
        if ($type === 'transfer' && $student->isActive() && empty($input['mark_left'])) {
            throw ValidationException::withMessages(['type' => __('The student is still active. Tick "mark as withdrawn" to issue a transfer certificate.')]);
        }
        if ($type === 'transfer' && Certificate::query()->where('student_id', $student->id)->where('type', 'transfer')->whereNull('revoked_at')->exists()) {
            throw ValidationException::withMessages(['type' => __('A transfer certificate has already been issued. Revoke it first to issue a new one.')]);
        }

        if ($type === 'achievement' && trim((string) ($input['title'] ?? '')) === '') {
            throw ValidationException::withMessages(['title' => __('Say what the certificate is awarded for.')]);
        }

        return DB::transaction(function () use ($student, $type, $input, $actor, $enrollment) {
            $leaving = $input['leaving_date'] ?? now()->toDateString();
            if ($type === 'transfer' && $student->isActive()) {
                $this->students->changeStatus($student, 'withdrawn', $leaving);
            }

            $father = $student->guardians->firstWhere('pivot.relation', 'father');
            $mother = $student->guardians->firstWhere('pivot.relation', 'mother');
            $year = now()->format('Y');

            $certificate = new Certificate([
                'student_id' => $student->id,
                'type' => $type,
                'issued_on' => now()->toDateString(),
                'issued_by' => $actor->id,
                'data' => array_filter([
                    'name' => $student->name,
                    'name_bn' => $student->name_bn,
                    'student_code' => $student->student_code,
                    'father' => $father?->name,
                    'mother' => $mother?->name,
                    'guardian' => $student->primaryGuardian()?->name,
                    'date_of_birth' => $student->date_of_birth?->toDateString(),
                    'birth_registration_no' => $student->birth_registration_no,
                    'admission_date' => $student->admission_date?->toDateString(),
                    'session' => $enrollment?->session?->name,
                    'class' => $enrollment?->schoolClass?->name,
                    'section' => $enrollment?->section?->name,
                    'group' => $enrollment?->studyGroup?->name,
                    'roll_no' => $enrollment?->roll_no,
                    'leaving_date' => $type === 'transfer' ? $leaving : null,
                    'reason' => $type === 'transfer' ? ($input['reason'] ?? null) : null,
                    'conduct' => $input['conduct'] ?? null,
                    'remarks' => $input['remarks'] ?? null,
                    'title' => $type === 'achievement' ? trim((string) $input['title']) : null,
                    'details' => $type === 'achievement' ? ($input['details'] ?? null) : null,
                ], fn ($v) => $v !== null && $v !== ''),
            ]);
            $certificate->serial_no = sprintf('%s-%s-%s-%04d', strtoupper($this->tenant->branch()->code), Certificate::CODES[$type],
                substr($year, -2), $this->sequences->next('certificate_'.$type, $year));
            $certificate->verification_code = $this->uniqueCode();
            $certificate->save();

            return $certificate;
        });
    }

    public function revoke(Certificate $certificate, string $reason): Certificate
    {
        if ($certificate->isRevoked()) {
            throw ValidationException::withMessages(['reason' => __('This certificate is already revoked.')]);
        }

        $certificate->forceFill(['revoked_at' => now(), 'revoke_reason' => $reason])->save();

        return $certificate;
    }

    protected function uniqueCode(): string
    {
        do {
            // Unambiguous characters only (no 0/O, 1/I) so the code is easy to type from paper.
            $code = substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 10);
        } while (Certificate::withoutGlobalScopes()->where('verification_code', $code)->exists());

        return $code;
    }
}
