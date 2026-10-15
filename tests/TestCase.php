<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Institution;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Seed roles & permissions once per test (RefreshDatabase wraps each test in a transaction). */
    protected bool $seedRoles = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Blade layouts call @vite; tests must not depend on a built manifest.
        $this->withoutVite();

        if ($this->seedRoles && in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }
    }

    protected function branchUrl(Branch $branch, string $path = '/'): string
    {
        return 'http://'.$branch->subdomain.'.'.config('tenancy.base_domain').'/'.ltrim($path, '/');
    }

    protected function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_domains')[0].'/'.ltrim($path, '/');
    }

    protected function makeBranch(?Institution $institution = null, array $attributes = []): Branch
    {
        return Branch::factory()->for($institution ?? Institution::factory()->create())->create($attributes);
    }

    /** A user with a role and explicit access to the given branch. */
    protected function branchUser(Branch $branch, string $role = 'branch-admin', array $attributes = []): User
    {
        return User::factory()->forBranch($branch)->role($role)->create($attributes);
    }

    /**
     * Sign in as $user on $branch the same way LoginController does (session bound to the tenant).
     */
    protected function actingOnBranch(User $user, Branch $branch): static
    {
        return $this->actingAs($user)->withSession(['tenant_key' => 'branch:'.$branch->id]);
    }

    protected function actingCentral(User $user): static
    {
        return $this->actingAs($user)->withSession(['tenant_key' => 'central']);
    }

    /**
     * Minimal academic structure for a branch: current year + open current session, class "Six" (VI)
     * with sections A (capacity 40) and B, class "Seven" (VII) with section A, and a Science group.
     *
     * @return array{session:\App\Models\Academic\AcademicSession, class:\App\Models\Academic\SchoolClass, sectionA:\App\Models\Academic\Section, sectionB:\App\Models\Academic\Section, next:\App\Models\Academic\SchoolClass, nextSection:\App\Models\Academic\Section, group:\App\Models\Academic\StudyGroup}
     */
    protected function academicSetup(Branch $branch, string $sessionName = '2026'): array
    {
        return $this->inBranch($branch, function () use ($sessionName) {
            $year = \App\Models\Academic\AcademicYear::query()->firstOrCreate(['name' => $sessionName],
                ['starts_on' => $sessionName.'-01-01', 'ends_on' => $sessionName.'-12-31', 'is_current' => true]);
            $session = \App\Models\Academic\AcademicSession::query()->create(['academic_year_id' => $year->id, 'name' => $sessionName,
                'starts_on' => $sessionName.'-01-01', 'ends_on' => $sessionName.'-12-31', 'is_current' => true, 'status' => 'open']);
            $class = \App\Models\Academic\SchoolClass::query()->create(['name' => 'Six', 'code' => 'VI', 'level' => 'junior_secondary', 'numeric_order' => 6, 'is_active' => true]);
            $next = \App\Models\Academic\SchoolClass::query()->create(['name' => 'Seven', 'code' => 'VII', 'level' => 'junior_secondary', 'numeric_order' => 7, 'is_active' => true]);

            return [
                'session' => $session,
                'class' => $class,
                'sectionA' => \App\Models\Academic\Section::query()->create(['school_class_id' => $class->id, 'name' => 'A', 'capacity' => 40, 'is_active' => true]),
                'sectionB' => \App\Models\Academic\Section::query()->create(['school_class_id' => $class->id, 'name' => 'B', 'capacity' => 40, 'is_active' => true]),
                'next' => $next,
                'nextSection' => \App\Models\Academic\Section::query()->create(['school_class_id' => $next->id, 'name' => 'A', 'capacity' => 40, 'is_active' => true]),
                'group' => \App\Models\Academic\StudyGroup::query()->create(['name' => 'Science', 'code' => 'SCI', 'is_active' => true]),
            ];
        });
    }

    /** Valid admission payload for POST /students. */
    protected function admissionPayload(array $setup, array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Rahim Uddin', 'gender' => 'male', 'date_of_birth' => '2014-03-15',
            'academic_session_id' => $setup['session']->id, 'school_class_id' => $setup['class']->id, 'section_id' => $setup['sectionA']->id,
            'guardians' => [['name' => 'Karim Uddin', 'phone' => '+8801711000001', 'relation' => 'father', 'is_primary' => 1]],
        ], $overrides);
    }

    /** Admit + enroll a student directly through the service (fast fixture). */
    protected function enrolStudent(Branch $branch, array $setup, string $name = 'Test Student', ?\App\Models\Academic\Section $section = null): \App\Models\Students\Student
    {
        return $this->inBranch($branch, fn () => app(\App\Services\Students\StudentService::class)->admit(
            ['name' => $name, 'gender' => 'male', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-05'],
            [['name' => 'Guardian of '.$name, 'phone' => '+88017'.random_int(10000000, 99999999), 'relation' => 'father']],
            ['academic_session_id' => $setup['session']->id, 'school_class_id' => ($section ?? $setup['sectionA'])->school_class_id, 'section_id' => ($section ?? $setup['sectionA'])->id],
        ));
    }

    /** Employee fixture, optionally linked to a login. */
    protected function makeEmployee(Branch $branch, array $attributes = [], ?User $user = null): \App\Models\HR\Employee
    {
        return $this->inBranch($branch, function () use ($attributes, $user) {
            $employee = app(\App\Services\HR\EmployeeService::class)->create(array_merge([
                'name' => 'Employee '.random_int(100, 999), 'gender' => 'female', 'employee_type' => 'teacher', 'employment_type' => 'permanent',
                'phone' => '+88018'.random_int(10000000, 99999999), 'joining_date' => '2020-01-01',
            ], $attributes), null, null);
            if ($user) {
                $employee->forceFill(['user_id' => $user->id])->save();
            }

            return $employee;
        });
    }

    protected function inBranch(Branch $branch, \Closure $callback): mixed
    {
        return app(TenantContext::class)->runForBranch($branch, $callback);
    }
}
