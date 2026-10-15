<?php

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\StaffCertificate;
use App\Models\Students\Certificate;
use App\Models\User;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Experience certificates (for staff who have left) and service certificates (for staff who are working now), with a serial
 * number, a public verification code and a snapshot of the facts stated. Salary is never printed.
 */
class StaffCertificateService
{
    public function __construct(protected TenantContext $tenant, protected SequenceService $sequences) {}

    /** @param array{conduct?:string, purpose?:string, remarks?:string} $input */
    public function issue(Employee $employee, string $type, array $input, User $actor): StaffCertificate
    {
        abort_unless(in_array($type, StaffCertificate::TYPES, true), 422);
        $employee->loadMissing(['designation:id,name,name_bn', 'department:id,name']);

        if ($type === 'service' && $employee->status !== 'active') {
            throw ValidationException::withMessages(['type' => __('A service certificate is only issued to a staff member who is working now. Use the experience certificate for someone who has left.')]);
        }
        if ($type === 'experience' && ! $employee->isSeparated()) {
            throw ValidationException::withMessages(['type' => __('An experience certificate is issued when the staff member has left (resigned, retired or terminated). Use the service certificate for current staff.')]);
        }

        return DB::transaction(function () use ($employee, $type, $input, $actor) {
            $year = now()->format('Y');
            $certificate = new StaffCertificate([
                'employee_id' => $employee->id, 'type' => $type, 'issued_on' => now()->toDateString(), 'issued_by' => $actor->id,
                'data' => array_filter([
                    'name' => $employee->name, 'name_bn' => $employee->name_bn, 'employee_code' => $employee->employee_code, 'gender' => $employee->gender,
                    'designation' => $employee->designation?->name, 'department' => $employee->department?->name, 'employment_type' => $employee->employment_type,
                    'joining_date' => $employee->joining_date?->toDateString(), 'leaving_date' => $type === 'experience' ? $employee->status_changed_on?->toDateString() : null,
                    'status' => $employee->status, 'conduct' => $input['conduct'] ?? null, 'purpose' => $input['purpose'] ?? null, 'remarks' => $input['remarks'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ]);
            $certificate->serial_no = sprintf('%s-%s-%s-%04d', strtoupper($this->tenant->branch()->code), StaffCertificate::CODES[$type], substr($year, -2),
                $this->sequences->next('staff_certificate_'.$type, $year));
            $certificate->verification_code = $this->uniqueCode();
            $certificate->save();

            return $certificate;
        });
    }

    public function revoke(StaffCertificate $certificate, string $reason): StaffCertificate
    {
        if ($certificate->isRevoked()) {
            throw ValidationException::withMessages(['reason' => __('This certificate is already revoked.')]);
        }
        $certificate->forceFill(['revoked_at' => now(), 'revoke_reason' => $reason])->save();

        return $certificate;
    }

    /** Unique across student and staff certificates (one public verification page serves both). */
    protected function uniqueCode(): string
    {
        do {
            $code = substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 10);
        } while (Certificate::withoutGlobalScopes()->where('verification_code', $code)->exists() || StaffCertificate::withoutGlobalScopes()->where('verification_code', $code)->exists());

        return $code;
    }
}
