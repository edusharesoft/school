<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_admin_manages_classes_sections_and_subjects(): void
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'academics/classes'), [
            'name' => 'Nine', 'code' => 'ix', 'level' => 'secondary', 'numeric_order' => 9, 'has_groups' => 1, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $class = $this->inBranch($branch, fn () => SchoolClass::query()->where('code', 'IX')->firstOrFail());
        $this->assertTrue($class->has_groups);

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'academics/sections'), [
            'school_class_id' => $class->id, 'name' => 'A', 'capacity' => 45, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'academics/subjects'), [
            'name' => 'Physics', 'code' => '136', 'type' => 'theory_practical', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'academics/classes'))->assertOk()->assertSee('Nine');
    }

    public function test_class_codes_are_unique_per_branch_only(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $this->academicSetup($a);
        $adminA = $this->branchUser($a, 'branch-admin');
        $adminB = $this->branchUser($b, 'branch-admin');

        $payload = ['name' => 'Six', 'code' => 'VI', 'level' => 'junior_secondary', 'numeric_order' => 6];

        $this->actingOnBranch($adminB, $b)->post($this->branchUrl($b, 'academics/classes'), $payload)->assertSessionHasNoErrors();
        $this->actingOnBranch($adminA, $a)->post($this->branchUrl($a, 'academics/classes'), $payload)->assertSessionHasErrors('code');
    }

    public function test_section_cannot_be_attached_to_another_branchs_class(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $foreign = $this->academicSetup($b);
        $admin = $this->branchUser($a, 'branch-admin');

        $this->actingOnBranch($admin, $a)->post($this->branchUrl($a, 'academics/sections'), [
            'school_class_id' => $foreign['class']->id, 'name' => 'Z', 'capacity' => 30,
        ])->assertSessionHasErrors('school_class_id');

        $this->assertSame(0, $this->inBranch($a, fn () => Section::query()->count()));
    }

    public function test_structure_of_other_branch_is_invisible(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $setupB = $this->academicSetup($b);
        $admin = $this->branchUser($a, 'branch-admin');

        $this->actingOnBranch($admin, $a)->get($this->branchUrl($a, "academics/classes/{$setupB['class']->id}/edit"))->assertNotFound();
        $this->assertSame(0, $this->inBranch($a, fn () => SchoolClass::query()->count()));
    }

    public function test_only_one_current_session_per_branch(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'academics/sessions'), [
            'academic_year_id' => $setup['session']->academic_year_id, 'name' => '2027', 'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31', 'status' => 'open', 'is_current' => 1,
        ])->assertSessionHasNoErrors();

        $current = $this->inBranch($branch, fn () => \App\Models\Academic\AcademicSession::query()->where('is_current', true)->pluck('name')->all());
        $this->assertSame(['2027'], $current);
    }

    public function test_class_with_enrollments_cannot_be_deleted(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup));

        $this->actingOnBranch($admin, $branch)->delete($this->branchUrl($branch, "academics/classes/{$setup['class']->id}"))
            ->assertSessionHasErrors('class');
    }

    public function test_teacher_cannot_manage_academic_structure(): void
    {
        $branch = $this->makeBranch();
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);

        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'academics/classes'))->assertForbidden();
    }
}
