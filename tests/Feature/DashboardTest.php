<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Department;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_dashboard_statistics_come_from_database_records(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $this->makeBranch($institution, ['status' => 'inactive']);
        $this->branchUser($a, 'teacher', ['user_type' => UserType::Teacher]);
        $this->branchUser($a, 'teacher', ['user_type' => UserType::Teacher]);
        $this->branchUser($b, 'student', ['user_type' => UserType::Student]);
        $this->inBranch($a, fn () => Department::factory()->count(3)->create());

        $admin = User::factory()->role('institution-admin')->create(['institution_id' => $institution->id]);

        $this->actingCentral($admin)->get($this->centralUrl('super-admin'))->assertOk();

        $this->actingCentral($admin)->getJson($this->centralUrl('super-admin/dashboard/data'))
            ->assertOk()
            ->assertJsonPath('cards.branches_total', 3)
            ->assertJsonPath('cards.branches_active', 2)
            ->assertJsonPath('cards.branches_inactive', 1)
            ->assertJsonPath('cards.teacher_accounts', 2)
            ->assertJsonPath('cards.student_accounts', 1)
            ->assertJsonPath('cards.departments', 3)
            ->assertJsonStructure(['charts' => ['branch_accounts', 'login_trend', 'branch_status', 'departments'], 'recent_activity']);
    }

    public function test_central_dashboard_excludes_other_institutions(): void
    {
        $mine = Institution::factory()->create();
        $this->makeBranch($mine);
        $this->makeBranch(Institution::factory()->create());
        $admin = User::factory()->role('institution-admin')->create(['institution_id' => $mine->id]);

        $this->actingCentral($admin)->getJson($this->centralUrl('super-admin/dashboard/data'))->assertJsonPath('cards.branches_total', 1);
    }

    public function test_branch_filter_cannot_target_a_foreign_branch(): void
    {
        $mine = Institution::factory()->create();
        $this->makeBranch($mine);
        $foreign = $this->makeBranch(Institution::factory()->create());
        $admin = User::factory()->role('institution-admin')->create(['institution_id' => $mine->id]);

        $this->actingCentral($admin)->getJson($this->centralUrl('super-admin/dashboard/data?branch_id='.$foreign->id))->assertNotFound();
    }

    public function test_branch_dashboard_only_counts_its_own_branch(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $this->inBranch($a, fn () => Department::factory()->count(2)->create());
        $this->inBranch($b, fn () => Department::factory()->count(5)->create());
        $admin = $this->branchUser($a, 'branch-admin');

        $this->actingOnBranch($admin, $a)->get($this->branchUrl($a, 'dashboard'))->assertOk();
        $this->actingOnBranch($admin, $a)->getJson($this->branchUrl($a, 'dashboard/data'))
            ->assertOk()->assertJsonPath('cards.departments', 2)->assertJsonPath('cards.staff_accounts', 1);
    }

    public function test_dashboard_export_is_csv(): void
    {
        $institution = Institution::factory()->create();
        $this->makeBranch($institution, ['name' => 'Export Campus']);
        $admin = User::factory()->role('institution-admin')->create(['institution_id' => $institution->id]);

        $response = $this->actingCentral($admin)->get($this->centralUrl('super-admin/dashboard/export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Export Campus', $response->streamedContent());
    }
}
