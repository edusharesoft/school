<?php

namespace App\Services\HR;

use App\Models\Branch;
use App\Models\HR\Employee;
use App\Models\HR\StaffBranchAssignment;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cross-branch staff assignments (central administration). An employee keeps one home branch (their
 * employee record); an assignment lets them work in another branch and grants their login access to it.
 */
class StaffAssignmentService
{
    public function __construct(protected TenantContext $tenant) {}

    public function assign(int $employeeId, int $branchId, ?string $duty, string $startsOn, ?string $endsOn, User $actor): StaffBranchAssignment
    {
        $institutionId = $this->tenant->institutionId();
        $employee = Employee::query()->withoutGlobalScope(BranchScope::class)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))->find($employeeId);
        $branch = Branch::query()->find($branchId);

        if (! $employee || ! $branch || $branch->institution_id !== $employee->institution_id) {
            throw ValidationException::withMessages(['branch_id' => __('Choose an employee and a branch of the same institution.')]);
        }
        if ($branch->id === $employee->branch_id) {
            throw ValidationException::withMessages(['branch_id' => __('This is already the employee’s home branch.')]);
        }
        if (! $employee->isActive()) {
            throw ValidationException::withMessages(['employee_id' => __('Only active employees can be assigned.')]);
        }
        if (StaffBranchAssignment::query()->where('employee_id', $employee->id)->where('branch_id', $branch->id)->current()->exists()) {
            throw ValidationException::withMessages(['branch_id' => __('The employee is already assigned to this branch.')]);
        }

        return DB::transaction(function () use ($employee, $branch, $duty, $startsOn, $endsOn, $actor) {
            $assignment = new StaffBranchAssignment([
                'employee_id' => $employee->id, 'branch_id' => $branch->id, 'duty' => $duty,
                'starts_on' => $startsOn, 'ends_on' => $endsOn, 'assigned_by' => $actor->id,
            ]);
            $assignment->institution_id = $employee->institution_id;
            $assignment->save();

            if ($employee->user) {
                $employee->user->branches()->syncWithoutDetaching([$branch->id => ['is_default' => false, 'granted_by' => $actor->id]]);
            }

            return $assignment;
        });
    }

    /** End an assignment today; branch access is removed unless another current assignment still needs it. */
    public function end(StaffBranchAssignment $assignment, User $actor): StaffBranchAssignment
    {
        return DB::transaction(function () use ($assignment) {
            $assignment->forceFill(['ends_on' => now()->subDay()->toDateString()])->save();

            $employee = Employee::query()->withoutGlobalScope(BranchScope::class)->find($assignment->employee_id);
            $stillNeeded = StaffBranchAssignment::query()->where('employee_id', $assignment->employee_id)
                ->where('branch_id', $assignment->branch_id)->current()->exists();

            if ($employee?->user && ! $stillNeeded && $employee->branch_id !== $assignment->branch_id) {
                $employee->user->branches()->detach($assignment->branch_id);
            }

            return $assignment;
        });
    }
}
