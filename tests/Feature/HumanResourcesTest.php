<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\UserType;
use App\Models\Attendance\StaffAttendance;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmploymentHistory;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0)); // a Tuesday (Friday is the default weekly holiday)
    }

    public function test_employee_is_created_with_generated_code_history_and_login_account(): void
    {
        $branch = $this->makeBranch(attributes: ['code' => 'BR01']);
        $admin = $this->branchUser($branch, 'branch-admin');
        $designation = $this->inBranch($branch, fn () => Designation::query()->create(['name' => 'Assistant Teacher', 'code' => 'AT', 'category' => 'teaching', 'rank' => 10, 'is_active' => true]));

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'hr/employees'), [
            'name' => 'Farzana Akter', 'gender' => 'female', 'employee_type' => 'teacher', 'employment_type' => 'permanent',
            'designation_id' => $designation->id, 'phone' => '01711-000111', 'joining_date' => '2026-01-01', 'basic_salary' => 30000,
            'create_account' => 1, 'account_email' => 'farzana@school.test', 'account_password' => 'Secret123', 'account_role' => 'teacher',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $employee = $this->inBranch($branch, fn () => Employee::query()->with('user')->firstOrFail());
        $this->assertSame('BR01-E0001', $employee->employee_code);
        $this->assertSame('01711000111', $employee->phone);
        $this->assertSame(UserType::Teacher, $employee->user->user_type);
        $this->assertTrue($employee->user->hasRole('teacher'));
        $this->assertTrue($employee->user->canAccessBranch($branch));
        $this->assertSame(1, $this->inBranch($branch, fn () => EmploymentHistory::query()->where('event', 'joined')->count()));
    }

    public function test_actor_cannot_grant_a_role_with_permissions_they_do_not_hold(): void
    {
        $branch = $this->makeBranch();
        $hr = $this->branchUser($branch, 'hr-manager'); // HR has users.create but not the teacher's academic permissions

        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, 'hr/employees'), [
            'name' => 'X', 'gender' => 'male', 'employee_type' => 'teacher', 'employment_type' => 'permanent', 'phone' => '01711000222',
            'joining_date' => '2026-01-01', 'create_account' => 1, 'account_email' => 'x@school.test', 'account_password' => 'Secret123', 'account_role' => 'branch-admin',
        ])->assertSessionHasErrors('account_role');

        $this->assertSame(0, User::query()->where('email', 'x@school.test')->count());
        $this->assertSame(0, $this->inBranch($branch, fn () => Employee::query()->count()), 'the whole creation is rolled back');
    }

    public function test_employee_records_are_isolated_between_branches(): void
    {
        $institution = \App\Models\Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $other = $this->makeEmployee($b, ['name' => 'Other Branch Teacher']);

        $this->actingOnBranch($this->branchUser($a, 'branch-admin'), $a)
            ->get($this->branchUrl($a, "hr/employees/{$other->id}"))->assertNotFound();
        $this->actingOnBranch($this->branchUser($a, 'branch-admin'), $a)
            ->get($this->branchUrl($a, 'hr/employees'))->assertOk()->assertDontSee('Other Branch Teacher');
    }

    public function test_salary_and_nid_are_hidden_without_the_salary_permission(): void
    {
        $branch = $this->makeBranch();
        $employee = $this->makeEmployee($branch, ['basic_salary' => 45678, 'nid_number' => '1234567890']);

        $this->actingOnBranch($this->branchUser($branch, 'principal'), $branch) // employees.view, no view-salary
            ->get($this->branchUrl($branch, "hr/employees/{$employee->id}"))
            ->assertOk()->assertDontSee('45,678')->assertDontSee('1234567890');

        $this->actingOnBranch($this->branchUser($branch, 'branch-admin'), $branch)
            ->get($this->branchUrl($branch, "hr/employees/{$employee->id}"))
            ->assertOk()->assertSee('45,678')->assertSee('1234567890');
    }

    public function test_separation_deactivates_the_login_and_is_recorded(): void
    {
        $branch = $this->makeBranch();
        $login = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $employee = $this->makeEmployee($branch, [], $login);

        $this->actingOnBranch($this->branchUser($branch, 'branch-admin'), $branch)
            ->patch($this->branchUrl($branch, "hr/employees/{$employee->id}/status"), ['status' => 'resigned', 'date' => '2026-03-01', 'reason' => 'Moved abroad'])
            ->assertSessionHasNoErrors();

        $this->assertSame('inactive', $login->fresh()->status->value);
        $this->assertSame('resigned', $employee->fresh()->status);
        $this->assertSame(1, $this->inBranch($branch, fn () => EmploymentHistory::query()->where('event', 'resigned')->count()));
    }

    public function test_leave_approval_is_written_into_staff_attendance_and_cancellation_reverts_it(): void
    {
        $branch = $this->makeBranch();
        $teacherUser = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $employee = $this->makeEmployee($branch, [], $teacherUser);
        $type = $this->inBranch($branch, fn () => LeaveType::query()->create(['name' => 'Casual', 'code' => 'CL', 'days_per_year' => 10, 'is_paid' => true, 'is_active' => true]));

        // Teacher applies from the teacher portal: Wed 11 → Sat 14 March = 3 working days (Friday 13 is a weekly holiday).
        $this->actingOnBranch($teacherUser, $branch)->post($this->branchUrl($branch, 'teacher/leaves'), [
            'leave_type_id' => $type->id, 'from_date' => '2026-03-11', 'to_date' => '2026-03-14', 'reason' => 'Family programme',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $application = $this->inBranch($branch, fn () => LeaveApplication::query()->firstOrFail());
        $this->assertSame(3.0, $application->days);
        $this->assertSame('pending', $application->status);

        $principal = $this->branchUser($branch, 'principal');
        $this->actingOnBranch($principal, $branch)->post($this->branchUrl($branch, "leaves/{$application->id}/approve"))->assertSessionHasNoErrors();

        $rows = $this->inBranch($branch, fn () => StaffAttendance::query()->where('employee_id', $employee->id)->orderBy('date')->get());
        $this->assertCount(3, $rows);
        $this->assertTrue($rows->every(fn ($r) => $r->status === AttendanceStatus::Leave && $r->leave_application_id === $application->id));

        $this->actingOnBranch($principal, $branch)->post($this->branchUrl($branch, "leaves/{$application->id}/cancel"))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->inBranch($branch, fn () => StaffAttendance::query()->where('employee_id', $employee->id)->count()));
    }

    public function test_leave_balance_overlap_and_self_approval_rules(): void
    {
        $branch = $this->makeBranch();
        $hrUser = $this->branchUser($branch, 'hr-manager');
        $hrEmployee = $this->makeEmployee($branch, ['employee_type' => 'staff'], $hrUser);
        $type = $this->inBranch($branch, fn () => LeaveType::query()->create(['name' => 'Sick', 'code' => 'SL', 'days_per_year' => 2, 'is_paid' => true, 'is_active' => true]));
        $url = $this->branchUrl($branch, 'leaves');

        $this->actingOnBranch($hrUser, $branch)->post($url, ['leave_type_id' => $type->id, 'from_date' => '2026-03-15', 'to_date' => '2026-03-18', 'reason' => 'Fever and rest'])
            ->assertSessionHasErrors('leave_type_id'); // 4 working days > 2 allowed

        $this->actingOnBranch($hrUser, $branch)->post($url, ['leave_type_id' => $type->id, 'from_date' => '2026-03-15', 'to_date' => '2026-03-16', 'reason' => 'Fever and rest'])
            ->assertSessionHasNoErrors();
        $this->actingOnBranch($hrUser, $branch)->post($url, ['leave_type_id' => $type->id, 'from_date' => '2026-03-16', 'is_half_day' => 1, 'reason' => 'Overlap'])
            ->assertSessionHasErrors('from_date');

        $application = $this->inBranch($branch, fn () => LeaveApplication::query()->where('employee_id', $hrEmployee->id)->firstOrFail());
        $this->actingOnBranch($hrUser, $branch)->post($this->branchUrl($branch, "leaves/{$application->id}/approve"))->assertForbidden();
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_staff_member_sees_only_own_leave_applications(): void
    {
        $branch = $this->makeBranch();
        $staffUser = $this->branchUser($branch, 'staff');
        $this->makeEmployee($branch, ['name' => 'Me Myself', 'employee_type' => 'staff'], $staffUser);
        $colleague = $this->makeEmployee($branch, ['name' => 'Colleague Person']);
        $type = $this->inBranch($branch, fn () => LeaveType::query()->create(['name' => 'Casual', 'code' => 'CL', 'days_per_year' => 10, 'is_paid' => true, 'is_active' => true]));
        $this->inBranch($branch, fn () => app(\App\Services\HR\LeaveService::class)->apply($colleague, $type, '2026-03-16', '2026-03-16', false, 'Colleague reason text', $staffUser));

        $this->actingOnBranch($staffUser, $branch)->get($this->branchUrl($branch, 'leaves?status=all'))
            ->assertOk()->assertDontSee('Colleague reason text');
    }
}
