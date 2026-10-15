<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Services\Tenancy\HandoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_branch_user_switches_with_single_use_token(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $user = $this->branchUser($a, 'hr-manager');
        $user->branches()->attach($b->id);

        $response = $this->actingOnBranch($user, $a)->post($this->branchUrl($a, "switch-branch/{$b->id}"));
        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringContainsString($b->subdomain.'.localhost', $target);
        $path = parse_url($target, PHP_URL_PATH);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get($this->branchUrl($b, $path))->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('branch:'.$b->id, session('tenant_key'));

        // Token is single-use.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($this->branchUrl($b, $path))->assertRedirectContains('/login');
        $this->assertGuest();
    }

    public function test_cannot_switch_to_a_branch_without_access(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $user = $this->branchUser($a, 'hr-manager');

        $this->actingOnBranch($user, $a)->post($this->branchUrl($a, "switch-branch/{$b->id}"))->assertForbidden();
    }

    public function test_token_issued_for_one_branch_is_useless_on_another(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $user = $this->branchUser($a, 'branch-admin');
        $user->branches()->attach($b->id);

        $url = app(HandoffService::class)->issue($user, $b);
        $path = parse_url($url, PHP_URL_PATH);

        $this->get($this->branchUrl($a, $path))->assertRedirectContains('/login');
        $this->assertGuest();
    }

    public function test_super_admin_opens_any_branch_from_central(): void
    {
        $root = User::factory()->platform()->role('super-admin')->create();
        $branch = $this->makeBranch();

        $response = $this->actingCentral($root)->post($this->centralUrl("super-admin/switch-branch/{$branch->id}"));
        $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($this->branchUrl($branch, $path))->assertRedirectContains('/dashboard');
        $this->assertAuthenticatedAs($root);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.handoff.used', 'user_id' => $root->id]);
    }
}
