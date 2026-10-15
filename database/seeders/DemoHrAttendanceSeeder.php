<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Section;
use App\Models\Branch;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\Institution;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Services\Attendance\AbsenceAlertService;
use App\Services\Attendance\AttendanceSettings;
use App\Services\Attendance\StaffAttendanceService;
use App\Services\HR\EmployeeService;
use App\Services\HR\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo HR + attendance data for every demo branch (local/staging only):
 * designations, leave types, employees (demo teacher/staff logins linked), class teachers,
 * the last 10 school days of student & staff attendance, a leave application and one absence streak.
 * Idempotent: a branch that already has designations is skipped.
 */
class DemoHrAttendanceSeeder extends Seeder
{
    /** code => [name, name_bn, category, rank] */
    protected array $designations = [
        'HT' => ['Principal / Head Teacher', 'অধ্যক্ষ / প্রধান শিক্ষক', 'teaching', 1],
        'AHT' => ['Assistant Head Teacher', 'সহকারী প্রধান শিক্ষক', 'teaching', 2],
        'LEC' => ['Lecturer', 'প্রভাষক', 'teaching', 5],
        'SAT' => ['Senior Assistant Teacher', 'সিনিয়র সহকারী শিক্ষক', 'teaching', 8],
        'AT' => ['Assistant Teacher', 'সহকারী শিক্ষক', 'teaching', 10],
        'ACC' => ['Accountant', 'হিসাবরক্ষক', 'non_teaching', 20],
        'OA' => ['Office Assistant', 'অফিস সহকারী', 'non_teaching', 30],
        'GRD' => ['Security Guard', 'নিরাপত্তা প্রহরী', 'non_teaching', 50],
    ];

    /** code => [name, name_bn, days, paid] */
    protected array $leaveTypes = [
        'CL' => ['Casual leave', 'নৈমিত্তিক ছুটি', 10, true],
        'SL' => ['Sick leave', 'অসুস্থতাজনিত ছুটি', 14, true],
        'EL' => ['Earned leave', 'অর্জিত ছুটি', 20, true],
        'ML' => ['Maternity leave', 'মাতৃত্বকালীন ছুটি', 120, true],
        'LWP' => ['Leave without pay', 'বিনা বেতনে ছুটি', 0, false],
    ];

    protected array $names = [
        ['Mahmudul Hasan', 'male', 'teacher', 'LEC'], ['Farzana Akter', 'female', 'teacher', 'AT'], ['Rafiqul Islam', 'male', 'teacher', 'SAT'],
        ['Shirin Sultana', 'female', 'teacher', 'AT'], ['Kamrul Hasan', 'male', 'teacher', 'AT'], ['Nasrin Jahan', 'female', 'teacher', 'LEC'],
        ['Abdul Karim', 'male', 'staff', 'OA'], ['Jamal Uddin', 'male', 'staff', 'GRD'],
    ];

    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SSC')->first();
        if (! $institution) {
            $this->command?->warn('Demo institution not found — run DemoInstitutionSeeder first.');

            return;
        }

        foreach (Branch::query()->where('institution_id', $institution->id)->with('institution')->ordered()->get() as $branch) {
            tenant()->runForBranch($branch, function () use ($branch) {
                if (Designation::query()->exists()) {
                    return;
                }

                mt_srand($branch->id * 7919); // repeatable demo data per branch

                DB::transaction(function () use ($branch) {
                    $this->seedStructure();
                    $this->seedEmployees($branch);
                    $this->assignClassTeachers($branch);
                });

                $this->seedAttendance();
                $this->seedLeave($branch);
                app(AbsenceAlertService::class)->detect();
            });
        }

        $this->command?->info('Demo HR & attendance data seeded.');
    }

    protected function seedStructure(): void
    {
        foreach ($this->designations as $code => [$name, $nameBn, $category, $rank]) {
            Designation::query()->create(['code' => $code, 'name' => $name, 'name_bn' => $nameBn, 'category' => $category, 'rank' => $rank, 'is_active' => true]);
        }
        foreach ($this->leaveTypes as $code => [$name, $nameBn, $days, $paid]) {
            LeaveType::query()->create(['code' => $code, 'name' => $name, 'name_bn' => $nameBn, 'days_per_year' => $days, 'is_paid' => $paid, 'is_active' => true]);
        }
    }

    protected function seedEmployees(Branch $branch): void
    {
        $service = app(EmployeeService::class);
        $actor = User::query()->where('email', "admin.branch{$branch->sort_order}@demo.test")->first();
        $designation = fn (string $code) => Designation::query()->where('code', $code)->value('id');
        $department = fn (string $code) => DB::table('departments')->where('branch_id', $branch->id)->where('code', $code)->value('id');
        $n = $branch->sort_order;

        // Demo logins become employees so attendance, leave and the teacher portal work out of the box.
        $linked = [
            ["principal.branch{$n}@demo.test", 'staff', 'HT', 'ADM'],
            ["classteacher.branch{$n}@demo.test", 'teacher', 'SAT', 'SCI'],
            ["teacher.branch{$n}@demo.test", 'teacher', 'AT', 'HUM'],
            ["accounts.branch{$n}@demo.test", 'staff', 'ACC', 'ACC'],
            ["admin.branch{$n}@demo.test", 'staff', 'OA', 'ADM'],
        ];

        foreach ($linked as $i => [$email, $type, $des, $dep]) {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                continue;
            }
            $employee = $service->create($this->profile($user->name, $i % 2 ? 'female' : 'male', $type, $des, $dep, $designation, $department, $user->email), null, $actor ?? $user);
            $employee->forceFill(['user_id' => $user->id])->save();
        }

        foreach ($this->names as $i => [$name, $gender, $type, $des]) {
            $service->create($this->profile($name, $gender, $type, $des, $type === 'teacher' ? ['SCI', 'HUM', 'BUS'][$i % 3] : 'ADM', $designation, $department), null, $actor);
        }
    }

    protected function profile(string $name, string $gender, string $type, string $des, string $dep, callable $designation, callable $department, ?string $email = null): array
    {
        return [
            'name' => $name,
            'gender' => $gender,
            'employee_type' => $type,
            'employment_type' => 'permanent',
            'designation_id' => $designation($des),
            'department_id' => $department($dep),
            'phone' => '+8801'.mt_rand(3, 9).str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            'email' => $email,
            'date_of_birth' => sprintf('%d-%02d-%02d', mt_rand(1970, 1996), mt_rand(1, 12), mt_rand(1, 28)),
            'joining_date' => sprintf('%d-%02d-01', mt_rand(2010, 2025), mt_rand(1, 12)),
            'qualification' => $type === 'teacher' ? 'M.Sc. / M.A., B.Ed.' : 'HSC',
            'experience_years' => mt_rand(1, 20),
            'basic_salary' => $type === 'teacher' ? mt_rand(22, 55) * 1000 : mt_rand(10, 20) * 1000,
            'blood_group' => ['A+', 'B+', 'O+', 'AB+'][mt_rand(0, 3)],
            'religion' => 'Islam',
        ];
    }

    /** The demo "Class Teacher" login is class teacher of Six-A; other sections get teacher users where available. */
    protected function assignClassTeachers(Branch $branch): void
    {
        $classTeacher = User::query()->where('email', "classteacher.branch{$branch->sort_order}@demo.test")->value('id');
        $section = Section::query()->whereHas('schoolClass', fn ($q) => $q->where('code', 'VI'))->where('name', 'A')->first();
        if ($classTeacher && $section) {
            $section->update(['class_teacher_id' => $classTeacher]);
        }
    }

    protected function seedAttendance(): void
    {
        $settings = app(AttendanceSettings::class);
        $staffService = app(StaffAttendanceService::class);
        $session = AcademicSession::query()->current()->first();
        if (! $session) {
            return;
        }

        $days = [];
        for ($d = CarbonImmutable::today(); count($days) < 10; $d = $d->subDay()) {
            if (! $settings->isWeeklyHoliday($d) && $d->greaterThanOrEqualTo(CarbonImmutable::parse($session->starts_on))) {
                $days[] = $d;
            }
            if ($d->lessThan(CarbonImmutable::parse($session->starts_on))) {
                break;
            }
        }
        $days = array_reverse($days);

        $branch = tenant()->branch();
        $enrollments = StudentEnrollment::query()->where('academic_session_id', $session->id)->where('status', 'enrolled')->get();
        $streakStudent = $enrollments->first()?->student_id; // absent for the last 4 register days → absence alert
        $now = now();

        $rows = [];
        foreach ($days as $index => $day) {
            foreach ($enrollments as $e) {
                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $e->student_id === $streakStudent && $index >= count($days) - 4 => AttendanceStatus::Absent,
                    $roll <= 84 => AttendanceStatus::Present,
                    $roll <= 90 => AttendanceStatus::Late,
                    $roll <= 97 => AttendanceStatus::Absent,
                    $roll <= 98 => AttendanceStatus::HalfDay,
                    default => AttendanceStatus::Leave,
                };
                $rows[] = [
                    'institution_id' => $branch->institution_id, 'branch_id' => $branch->id,
                    'academic_session_id' => $session->id, 'student_id' => $e->student_id, 'student_enrollment_id' => $e->id,
                    'school_class_id' => $e->school_class_id, 'section_id' => $e->section_id, 'subject_id' => null, 'slot' => 'day',
                    'date' => $day->toDateString(), 'status' => $status->value,
                    'check_in_at' => in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true)
                        ? sprintf('%02d:%02d:00', $status === AttendanceStatus::Late ? 8 : 7, $status === AttendanceStatus::Late ? mt_rand(15, 45) : mt_rand(30, 59)) : null,
                    'method' => mt_rand(0, 1) ? 'manual' : 'qr', 'remarks' => null, 'marked_by' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('student_attendances')->insert($chunk);
        }

        $rows = [];
        foreach ($days as $day) {
            foreach (Employee::query()->where('status', 'active')->get() as $emp) {
                $roll = mt_rand(1, 100);
                if ($roll > 94) {
                    $rows[] = $this->staffRow($emp, $day, AttendanceStatus::Absent, null, null, $staffService);
                    continue;
                }
                $in = CarbonImmutable::parse($day->toDateString().' 07:25')->addMinutes(mt_rand(0, $roll > 85 ? 50 : 25));
                $out = $day->isToday() ? null : CarbonImmutable::parse($day->toDateString().' 14:20')->addMinutes(mt_rand(0, 90));
                $timings = $staffService->timings($day, $in, $out);
                $rows[] = $this->staffRow($emp, $day, $timings['late_minutes'] > 0 ? AttendanceStatus::Late : AttendanceStatus::Present, $in, $out, $staffService);
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('staff_attendances')->insert($chunk);
        }
    }

    protected function staffRow(Employee $emp, CarbonImmutable $day, AttendanceStatus $status, ?CarbonImmutable $in, ?CarbonImmutable $out, StaffAttendanceService $service): array
    {
        return $service->timings($day, $in, $out) + [
            'institution_id' => $emp->institution_id, 'branch_id' => $emp->branch_id, 'employee_id' => $emp->id,
            'date' => $day->toDateString(), 'status' => $status->value,
            'check_in_at' => $in?->toDateTimeString(), 'check_out_at' => $out?->toDateTimeString(),
            'method' => $in ? (mt_rand(0, 1) ? 'biometric' : 'manual') : 'manual', 'leave_application_id' => null,
            'remarks' => null, 'marked_by' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    protected function seedLeave(Branch $branch): void
    {
        $teacher = Employee::query()->whereHas('user', fn ($q) => $q->where('email', "teacher.branch{$branch->sort_order}@demo.test"))->first();
        $type = LeaveType::query()->where('code', 'CL')->first();
        $applicant = $teacher?->user;
        if (! $teacher || ! $type || ! $applicant) {
            return;
        }

        $from = CarbonImmutable::today()->addWeekdays(5);
        if (! LeaveApplication::query()->where('employee_id', $teacher->id)->exists()) {
            app(LeaveService::class)->apply($teacher, $type, $from->toDateString(), $from->addDay()->toDateString(), false,
                'Family programme in the village home.', $applicant);
        }
    }
}
