<?php

namespace Tests\Feature;

use App\Exceptions\CrossTenantViolation;
use App\Exceptions\TenantContextMissing;
use App\Models\Department;
use App\Models\Institution;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_scoped_to_the_current_branch(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);

        $this->inBranch($a, fn () => Department::factory()->count(3)->create());
        $this->inBranch($b, fn () => Department::factory()->count(2)->create());

        $this->assertSame(3, $this->inBranch($a, fn () => Department::query()->count()));
        $this->assertSame(2, $this->inBranch($b, fn () => Department::query()->count()));
    }

    public function test_without_tenant_context_queries_return_nothing(): void
    {
        $branch = $this->makeBranch();
        $this->inBranch($branch, fn () => Department::factory()->count(2)->create());

        app(TenantContext::class)->clear();

        $this->assertSame(0, Department::query()->count(), 'Fail-closed scope must hide rows when no tenant is set.');
    }

    public function test_writing_without_tenant_context_throws(): void
    {
        app(TenantContext::class)->clear();

        $this->expectException(TenantContextMissing::class);
        Department::query()->create(['name' => 'X', 'code' => 'X']);
    }

    public function test_payload_with_foreign_branch_id_is_rejected(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);

        $this->expectException(CrossTenantViolation::class);
        $this->inBranch($a, function () use ($b) {
            $d = new Department(['name' => 'Injected', 'code' => 'INJ']);
            $d->branch_id = $b->id;
            $d->save();
        });
    }

    public function test_branch_id_is_not_mass_assignable(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);

        $dept = $this->inBranch($a, fn () => Department::query()->create(['name' => 'Science', 'code' => 'SCI', 'branch_id' => $b->id]));

        $this->assertSame($a->id, (int) $dept->branch_id);
    }

    public function test_owning_branch_cannot_be_changed(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $dept = $this->inBranch($a, fn () => Department::factory()->create());

        $this->expectException(CrossTenantViolation::class);
        $this->inBranch($a, function () use ($dept, $b) {
            $dept->branch_id = $b->id;
            $dept->save();
        });
    }

    public function test_branch_user_cannot_open_another_branchs_record_by_changing_the_url_id(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $foreign = $this->inBranch($b, fn () => Department::factory()->create());
        $admin = $this->branchUser($a, 'branch-admin');

        $this->actingOnBranch($admin, $a)
            ->get($this->branchUrl($a, "departments/{$foreign->id}/edit"))
            ->assertNotFound();

        $this->actingOnBranch($admin, $a)
            ->put($this->branchUrl($a, "departments/{$foreign->id}"), ['name' => 'Hacked', 'code' => 'HCK'])
            ->assertNotFound();

        $this->assertDatabaseMissing('departments', ['name' => 'Hacked']);
    }

    public function test_branch_user_cannot_sign_in_to_another_branch(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $admin = $this->branchUser($a, 'branch-admin', ['email' => 'a-admin@test.bd']);

        $this->post($this->branchUrl($b, 'login'), ['login' => 'a-admin@test.bd', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_session_from_one_branch_is_rejected_on_another(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        // User legitimately has access to both, but the session was issued on branch A.
        $user = $this->branchUser($a, 'branch-admin');
        $user->branches()->attach($b->id);

        $this->actingOnBranch($user, $a)
            ->get($this->branchUrl($b, 'dashboard'))
            ->assertRedirectContains('/login');
    }

    public function test_institution_admin_cannot_see_other_institutions_branches(): void
    {
        $mine = Institution::factory()->create();
        $other = Institution::factory()->create();
        $myBranch = $this->makeBranch($mine);
        $otherBranch = $this->makeBranch($other, ['name' => 'Foreign Campus']);
        $admin = \App\Models\User::factory()->role('institution-admin')->create(['institution_id' => $mine->id]);

        $this->actingCentral($admin)->get($this->centralUrl('super-admin/branches'))
            ->assertOk()->assertSee($myBranch->name)->assertDontSee('Foreign Campus');

        $this->actingCentral($admin)->get($this->centralUrl("super-admin/branches/{$otherBranch->id}"))->assertForbidden();
    }

    public function test_institution_admin_on_a_branch_of_another_institution_is_denied(): void
    {
        $mine = Institution::factory()->create();
        $other = Institution::factory()->create();
        $otherBranch = $this->makeBranch($other);
        $admin = \App\Models\User::factory()->role('institution-admin')->create(['institution_id' => $mine->id]);

        $this->assertFalse($admin->canAccessBranch($otherBranch));
    }
}
