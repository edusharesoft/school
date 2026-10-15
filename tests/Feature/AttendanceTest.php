<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\UserType;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendancePunch;
use App\Models\Attendance\StaffAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Models\Institution;
use App\Services\Attendance\AbsenceAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0)); // Tuesday; Friday is the default weekly holiday
    }

    protected function register(array $setup, array $students, string $date = '2026-03-10', array $statuses = []): array
    {
        $entries = [];
        foreach ($students as $i => $s) {
            $entries[$s->id] = ['status' => $statuses[$i] ?? 'present'];
        }

        return ['section_id' => $setup['sectionA']->id, 'date' => $date, 'entries' => $entries];
    }

    public function test_register_is_saved_once_per_student_per_day_and_resaving_today_updates(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $b = $this->enrolStudent($branch, $setup, 'Babul');
        $admin = $this->branchUser($branch, 'branch-admin');
        $url = $this->branchUrl($branch, 'attendance/students');

        $this->actingOnBranch($admin, $branch)->post($url, $this->register($setup, [$a, $b], statuses: ['present', 'absent']))->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->post($url, $this->register($setup, [$a, $b], statuses: ['present', 'late']))->assertSessionHasNoErrors();

        $rows = $this->inBranch($branch, fn () => StudentAttendance::query()->orderBy('student_id')->get());
        $this->assertCount(2, $rows);
        $this->assertSame(AttendanceStatus::Late, $rows[1]->status);
        $this->assertSame('day', $rows[0]->slot);
    }

    public function test_future_dates_holidays_and_foreign_sections_are_rejected(): void
    {
        $institution = Institution::factory()->create();
        $branch = $this->makeBranch($institution);
        $other = $this->makeBranch($institution);
        $setup = $this->academicSetup($branch);
        $otherSetup = $this->academicSetup($other);
        $student = $this->enrolStudent($branch, $setup);
        $admin = $this->branchUser($branch, 'branch-admin');
        $url = $this->branchUrl($branch, 'attendance/students');

        $this->actingOnBranch($admin, $branch)->post($url, $this->register($setup, [$student], '2026-03-11'))->assertSessionHasErrors('date');
        $this->actingOnBranch($admin, $branch)->post($url, $this->register($setup, [$student], '2026-03-06'))->assertSessionHasErrors('date'); // Friday
        $this->actingOnBranch($admin, $branch)->post($url, ['section_id' => $otherSetup['sectionA']->id] + $this->register($setup, [$student]))
            ->assertSessionHasErrors('section_id');

        $this->assertSame(0, $this->inBranch($branch, fn () => StudentAttendance::query()->count()));
    }

    public function test_teacher_may_mark_only_the_section_they_are_class_teacher_of(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $student = $this->enrolStudent($branch, $setup);
        $studentB = $this->enrolStudent($branch, $setup, 'B student', $setup['sectionB']);
        $teacher = $this->branchUser($branch, 'class-teacher', ['user_type' => UserType::Teacher]);
        $this->inBranch($branch, fn () => $setup['sectionA']->update(['class_teacher_id' => $teacher->id]));

        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'teacher/attendance'))->assertOk()
            ->assertSee('teacher/attendance/sections/'.$setup['sectionA']->id.'?', false)
            ->assertDontSee('teacher/attendance/sections/'.$setup['sectionB']->id.'?', false);
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'teacher/attendance'), $this->register($setup, [$student]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'teacher/attendance'), [
            'section_id' => $setup['sectionB']->id, 'date' => '2026-03-10', 'entries' => [$studentB->id => ['status' => 'present']],
        ])->assertForbidden();
        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, "teacher/attendance/sections/{$setup['sectionB']->id}"))->assertForbidden();

        // Teachers never reach the back-office attendance pages.
        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'attendance/students'))->assertForbidden();
    }

    public function test_past_register_is_locked_and_changed_only_through_an_approved_correction(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $student = $this->enrolStudent($branch, $setup);
        $teacher = $this->branchUser($branch, 'class-teacher', ['user_type' => UserType::Teacher]);
        $this->inBranch($branch, fn () => $setup['sectionA']->update(['class_teacher_id' => $teacher->id]));

        $this->travelTo(now()->setDate(2026, 3, 9)->setTime(9, 0));
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'teacher/attendance'), $this->register($setup, [$student], '2026-03-09', ['absent']))
            ->assertSessionHasNoErrors();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        // Next day: re-saving yesterday is refused.
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'teacher/attendance'), $this->register($setup, [$student], '2026-03-09', ['present']))
            ->assertSessionHasErrors('date');

        $record = $this->inBranch($branch, fn () => StudentAttendance::query()->firstOrFail());
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'teacher/attendance/corrections'), [
            'type' => 'student', 'record_id' => $record->id, 'requested_status' => 'late', 'reason' => 'Came at 9:10 with a note',
        ])->assertSessionHasNoErrors();

        $correction = $this->inBranch($branch, fn () => AttendanceCorrection::query()->firstOrFail());
        $this->assertSame('pending', $correction->status);
        $this->assertSame(AttendanceStatus::Absent, $record->fresh()->status, 'unchanged until approved');

        $principal = $this->branchUser($branch, 'principal');
        $this->actingOnBranch($principal, $branch)->post($this->branchUrl($branch, "attendance/corrections/{$correction->id}/approve"))
            ->assertSessionHasNoErrors();

        $this->assertSame(AttendanceStatus::Late, $record->fresh()->status);
        $this->assertSame('approved', $correction->fresh()->status);
        $this->assertSame($principal->id, $correction->fresh()->reviewed_by);
    }

    public function test_scan_station_marks_students_late_after_grace_and_employees_check_in_then_out(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $student = $this->enrolStudent($branch, $setup);
        $employee = $this->makeEmployee($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $url = $this->branchUrl($branch, 'attendance/scan');

        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(8, 20)); // student start 08:00 + 10 min grace → late
        $this->actingOnBranch($admin, $branch)->postJson($url, ['code' => strtolower($student->student_code)])
            ->assertOk()->assertJsonPath('state', 'marked');
        $this->actingOnBranch($admin, $branch)->postJson($url, ['code' => $student->student_code])
            ->assertOk()->assertJsonPath('state', 'duplicate');

        $this->actingOnBranch($admin, $branch)->postJson($url, ['code' => $employee->employee_code])->assertOk()->assertJsonPath('state', 'check_in');
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(15, 30));
        $this->actingOnBranch($admin, $branch)->postJson($url, ['code' => $employee->employee_code])->assertOk()->assertJsonPath('state', 'check_out');

        $this->assertSame(AttendanceStatus::Late, $this->inBranch($branch, fn () => StudentAttendance::query()->firstOrFail()->status));
        $staff = $this->inBranch($branch, fn () => StaffAttendance::query()->firstOrFail());
        $this->assertSame(AttendanceStatus::Late, $staff->status);   // 08:20 vs 07:45 + 10
        $this->assertSame(35, $staff->late_minutes);
        $this->assertSame(60, $staff->overtime_minutes);             // 15:30 vs 14:30
        $this->assertSame(430, $staff->worked_minutes);

        $this->actingOnBranch($admin, $branch)->postJson($url, ['code' => 'NOPE-123'])->assertNotFound();
    }

    public function test_device_punches_are_authenticated_branch_bound_and_idempotent(): void
    {
        $institution = Institution::factory()->create();
        $branch = $this->makeBranch($institution);
        $other = $this->makeBranch($institution);
        $employee = $this->makeEmployee($branch);
        [$device, $key] = $this->inBranch($branch, function () {
            $device = new AttendanceDevice(['name' => 'Gate', 'type' => 'biometric', 'is_active' => true]);
            $key = $device->issueKey();
            $device->save();

            return [$device, $key];
        });
        $payload = ['punches' => [['code' => $employee->employee_code, 'time' => '2026-03-10 07:40:00']]];

        $this->postJson($this->branchUrl($branch, 'api/v1/attendance/punches'), $payload, ['X-Device-Key' => 'dev_wrong'])->assertUnauthorized();
        $this->postJson($this->branchUrl($other, 'api/v1/attendance/punches'), $payload, ['X-Device-Key' => $key])->assertUnauthorized();

        $this->postJson($this->branchUrl($branch, 'api/v1/attendance/punches'), $payload, ['X-Device-Key' => $key])
            ->assertOk()->assertJsonPath('data.applied', 1);
        $this->postJson($this->branchUrl($branch, 'api/v1/attendance/punches'), $payload, ['X-Device-Key' => $key])
            ->assertOk()->assertJsonPath('data.duplicate', 1)->assertJsonPath('data.applied', 0);

        $this->assertSame(1, $this->inBranch($branch, fn () => AttendancePunch::query()->count()));
        $record = $this->inBranch($branch, fn () => StaffAttendance::query()->firstOrFail());
        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertSame('biometric', $record->method);

        $this->inBranch($branch, fn () => $device->update(['is_active' => false]));
        $this->postJson($this->branchUrl($branch, 'api/v1/attendance/punches'), $payload, ['X-Device-Key' => $key])->assertUnauthorized();
    }

    public function test_staff_register_calculates_times_and_keeps_approved_leave(): void
    {
        $branch = $this->makeBranch();
        $a = $this->makeEmployee($branch, ['name' => 'Alpha']);
        $b = $this->makeEmployee($branch, ['name' => 'Beta']);
        $this->inBranch($branch, fn () => StaffAttendance::query()->create(['employee_id' => $b->id, 'date' => '2026-03-10', 'status' => 'leave', 'leave_application_id' => null, 'method' => 'manual']));
        $hr = $this->branchUser($branch, 'hr-manager');

        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, 'attendance/staff'), ['date' => '2026-03-10', 'entries' => [
            $a->id => ['status' => 'present', 'check_in' => '08:05', 'check_out' => '14:00'],
        ]])->assertSessionHasNoErrors();

        $row = $this->inBranch($branch, fn () => StaffAttendance::query()->where('employee_id', $a->id)->firstOrFail());
        $this->assertSame(20, $row->late_minutes);          // 08:05 vs 07:45 (grace 10)
        $this->assertSame(30, $row->early_leave_minutes);   // left 14:00 vs 14:30
        $this->assertSame(AttendanceStatus::Present, $row->status, 'a manually chosen status is kept');
        $this->assertSame(AttendanceStatus::Leave, $this->inBranch($branch, fn () => StaffAttendance::query()->where('employee_id', $b->id)->firstOrFail()->status));
    }

    public function test_consecutive_absences_raise_one_alert_per_streak(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $absent = $this->enrolStudent($branch, $setup, 'Absent Kid');
        $present = $this->enrolStudent($branch, $setup, 'Present Kid');
        $admin = $this->branchUser($branch, 'branch-admin');

        foreach (['2026-03-07', '2026-03-08', '2026-03-09', '2026-03-10'] as $day) { // Sat–Tue
            $this->travelTo(\Illuminate\Support\Carbon::parse($day.' 09:00'));
            $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'attendance/students'),
                $this->register($setup, [$absent, $present], $day, ['absent', 'present']))->assertSessionHasNoErrors();
        }

        $stats = $this->inBranch($branch, fn () => app(AbsenceAlertService::class)->detect());
        $this->assertSame(1, $stats['created']);
        $this->inBranch($branch, fn () => app(AbsenceAlertService::class)->detect()); // idempotent
        Artisan::call('tenancy:run', ['cmd' => 'attendance:absence-alerts', '--branch' => [$branch->id]]);

        $alerts = $this->inBranch($branch, fn () => AttendanceAlert::query()->get());
        $this->assertCount(1, $alerts);
        $this->assertSame($absent->id, $alerts[0]->student_id);
        $this->assertSame(4, $alerts[0]->streak_days);
    }

    public function test_dashboard_and_monthly_sheet_use_recorded_attendance(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $b = $this->enrolStudent($branch, $setup, 'Babul');
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'attendance/students'), $this->register($setup, [$a, $b], statuses: ['present', 'absent']));

        $dashboard = $this->actingOnBranch($admin, $branch)->getJson($this->branchUrl($branch, 'dashboard/data'))->assertOk()
            ->assertJsonPath('cards.students_present_today', 1)
            ->assertJsonPath('cards.students_absent_today', 1)
            ->assertJsonStructure(['charts' => ['attendance_trend', 'class_attendance', 'staff_status_today']]);
        // 50.0 may be encoded as 50 or 50.0 depending on php.ini serialize_precision — compare numerically.
        $this->assertEquals(50, $dashboard->json('cards.student_attendance_pct_today'));

        $csv = $this->actingOnBranch($admin, $branch)
            ->get($this->branchUrl($branch, "attendance/reports/students?month=2026-03&section_id={$setup['sectionA']->id}&export=csv"))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Anika', $csv);
        $this->assertStringContainsString('100', $csv); // Anika's percentage
    }
}
