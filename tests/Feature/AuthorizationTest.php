<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_cannot_manage_departments_even_by_direct_url(): void
    {
        $branch = $this->makeBranch();
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);

        // Teachers use the teacher portal; admin area is blocked regardless of menus.
        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'departments'))->assertForbidden();
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'departments'), ['name' => 'X', 'code' => 'X'])->assertForbidden();
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $branch = $this->makeBranch();
        $librarian = $this->branchUser($branch, 'librarian');

        $this->actingOnBranch($librarian, $branch)->get($this->branchUrl($branch, 'departments'))->assertForbidden();
        $this->actingOnBranch($librarian, $branch)->get($this->branchUrl($branch, 'dashboard'))->assertForbidden();
    }

    public function test_branch_admin_can_crud_departments(): void
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)
            ->post($this->branchUrl($branch, 'departments'), ['name' => 'Science', 'code' => 'sci', 'is_active' => 1])
            ->assertRedirectContains('/departments');

        $dept = $this->inBranch($branch, fn () => Department::query()->where('code', 'SCI')->firstOrFail());
        $this->assertSame($branch->id, (int) $dept->branch_id);
        $this->assertSame($branch->institution_id, (int) $dept->institution_id);

        $this->actingOnBranch($admin, $branch)
            ->put($this->branchUrl($branch, "departments/{$dept->id}"), ['name' => 'Science & Tech', 'code' => 'SCI', 'is_active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'Science & Tech']);

        $this->actingOnBranch($admin, $branch)->delete($this->branchUrl($branch, "departments/{$dept->id}"))->assertRedirect();
        $this->assertSoftDeleted('departments', ['id' => $dept->id]);
    }

    public function test_department_code_is_unique_per_branch_only(): void
    {
        $institution = \App\Models\Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $this->inBranch($a, fn () => Department::factory()->create(['code' => 'SCI']));
        $adminB = $this->branchUser($b, 'branch-admin');
        $adminA = $this->branchUser($a, 'branch-admin');

        $this->actingOnBranch($adminB, $b)->post($this->branchUrl($b, 'departments'), ['name' => 'Science', 'code' => 'SCI'])
            ->assertSessionHasNoErrors();

        $this->actingOnBranch($adminA, $a)->post($this->branchUrl($a, 'departments'), ['name' => 'Science 2', 'code' => 'SCI'])
            ->assertSessionHasErrors('code');
    }

    public function test_branch_admin_cannot_use_central_area(): void
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingCentral($admin)->get($this->centralUrl('super-admin'))->assertRedirectContains('/login');
    }

    public function test_institution_admin_cannot_create_or_edit_roles(): void
    {
        $admin = User::factory()->role('institution-admin')->create();

        $this->actingCentral($admin)->get($this->centralUrl('super-admin/roles'))->assertOk();
        $this->actingCentral($admin)->get($this->centralUrl('super-admin/roles/create'))->assertForbidden();
        $this->actingCentral($admin)->post($this->centralUrl('super-admin/roles'), ['name' => 'x', 'label' => 'X', 'scope' => 'branch', 'portal' => 'admin'])
            ->assertForbidden();
    }

    public function test_non_super_admin_cannot_grant_super_admin_role(): void
    {
        $admin = User::factory()->role('institution-admin')->create();
        $branch = $this->makeBranch($admin->institution);

        $this->actingCentral($admin)->post($this->centralUrl('super-admin/users'), [
            'name' => 'Escalation', 'email' => 'esc@test.bd', 'user_type' => 'staff', 'status' => 'active',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'roles' => ['super-admin'], 'branches' => [$branch->id],
        ])->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'esc@test.bd']);
    }

    public function test_user_cannot_be_given_a_branch_of_another_institution(): void
    {
        $admin = User::factory()->role('institution-admin')->create();
        $foreignBranch = $this->makeBranch();

        $this->actingCentral($admin)->post($this->centralUrl('super-admin/users'), [
            'name' => 'Cross', 'email' => 'cross@test.bd', 'user_type' => 'staff', 'status' => 'active',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'roles' => ['accountant'], 'branches' => [$foreignBranch->id],
        ])->assertSessionHasErrors('branches');
    }

    public function test_super_admin_passes_every_gate(): void
    {
        $root = User::factory()->platform()->role('super-admin')->create();

        $this->assertTrue($root->can('anything.at-all'));
        $this->assertTrue($root->canAccessAllBranches());
    }
}
