<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function root(): User
    {
        return User::factory()->platform()->role('super-admin')->create();
    }

    public function test_super_admin_creates_a_branch_with_subdomain(): void
    {
        $institution = Institution::factory()->create();

        $this->actingCentral($this->root())->post($this->centralUrl('super-admin/branches'), [
            'institution_id' => $institution->id, 'name' => 'Uttara Campus', 'code' => 'utt', 'subdomain' => 'Uttara',
            'type' => 'school_college', 'timezone' => 'Asia/Dhaka', 'currency' => 'BDT', 'locale' => 'bn', 'status' => 'active',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', ['subdomain' => 'uttara', 'code' => 'UTT', 'institution_id' => $institution->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => Branch::class]);
    }

    public function test_reserved_and_duplicate_subdomains_are_rejected(): void
    {
        $institution = Institution::factory()->create();
        $this->makeBranch($institution, ['subdomain' => 'branch1']);
        $root = $this->root();

        foreach (['www', 'api', 'branch1', 'Bad_Name'] as $sub) {
            $this->actingCentral($root)->post($this->centralUrl('super-admin/branches'), [
                'institution_id' => $institution->id, 'name' => 'X', 'code' => 'X'.rand(10, 99), 'subdomain' => $sub,
                'type' => 'school', 'timezone' => 'Asia/Dhaka', 'currency' => 'BDT', 'locale' => 'en', 'status' => 'active',
            ])->assertSessionHasErrors('subdomain');
        }
    }

    public function test_deactivating_a_branch_takes_it_offline(): void
    {
        $branch = $this->makeBranch();
        $this->get($this->branchUrl($branch, 'login'))->assertOk();

        $this->actingCentral($this->root())->patch($this->centralUrl("super-admin/branches/{$branch->id}/status"))->assertRedirect();

        $this->assertSame('inactive', $branch->fresh()->status->value);
        $this->get($this->branchUrl($branch, 'login'))->assertStatus(503);
    }

    public function test_super_admin_creates_user_with_roles_and_branch_access(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);

        $this->actingCentral($this->root())->post($this->centralUrl('super-admin/users'), [
            'institution_id' => $institution->id, 'name' => 'Rahim Uddin', 'email' => 'Rahim@Test.bd', 'phone' => '+8801712345678',
            'user_type' => 'staff', 'status' => 'active', 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'roles' => ['accountant'], 'branches' => [$a->id, $b->id], 'default_branch_id' => $b->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'rahim@test.bd')->firstOrFail();
        $this->assertTrue($user->hasRole('accountant'));
        $this->assertSame($institution->id, $user->institution_id);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $user->branches->pluck('id')->all());
        $this->assertSame($b->id, $user->default_branch_id);
        $this->assertTrue($user->canAccessBranch($a));
    }

    public function test_super_admin_creates_custom_role_with_permissions(): void
    {
        $this->actingCentral($this->root())->post($this->centralUrl('super-admin/roles'), [
            'name' => 'Exam Assistant', 'label' => 'Exam Assistant', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => ['marks.view', 'marks.enter'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $role = \Spatie\Permission\Models\Role::findByName('exam-assistant');
        $this->assertEqualsCanonicalizing(['marks.view', 'marks.enter'], $role->permissions->pluck('name')->all());
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $role = \Spatie\Permission\Models\Role::findByName('teacher');

        $this->actingCentral($this->root())->delete($this->centralUrl("super-admin/roles/{$role->id}"))->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
