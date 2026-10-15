<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_on_branch(): void
    {
        $branch = $this->makeBranch();

        $this->get($this->branchUrl($branch, 'login'))->assertOk()->assertSee(__('Sign in'));
    }

    public function test_branch_admin_can_sign_in_with_email_and_lands_on_dashboard(): void
    {
        $branch = $this->makeBranch();
        $this->branchUser($branch, 'branch-admin', ['email' => 'admin@test.bd']);

        $this->post($this->branchUrl($branch, 'login'), ['login' => 'admin@test.bd', 'password' => 'password'])
            ->assertRedirectContains('/dashboard');

        $this->assertAuthenticated();
        $this->assertSame('branch:'.$branch->id, session('tenant_key'));
    }

    public function test_user_can_sign_in_with_phone_number(): void
    {
        $branch = $this->makeBranch();
        $this->branchUser($branch, 'teacher', ['phone' => '+8801711111111', 'user_type' => UserType::Teacher]);

        $this->post($this->branchUrl($branch, 'login'), ['login' => '+8801711111111', 'password' => 'password'])
            ->assertRedirectContains('/teacher');
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        $branch = $this->makeBranch();
        $this->branchUser($branch, 'branch-admin', ['email' => 'admin@test.bd']);

        $this->post($this->branchUrl($branch, 'login'), ['login' => 'admin@test.bd', 'password' => 'wrong'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.failed', 'branch_id' => $branch->id]);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $branch = $this->makeBranch();
        $this->branchUser($branch, 'branch-admin', ['email' => 'off@test.bd', 'status' => 'inactive']);

        $this->post($this->branchUrl($branch, 'login'), ['login' => 'off@test.bd', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $branch = $this->makeBranch();

        foreach (range(1, 5) as $i) {
            $this->post($this->branchUrl($branch, 'login'), ['login' => 'x@test.bd', 'password' => 'bad']);
        }

        $this->post($this->branchUrl($branch, 'login'), ['login' => 'x@test.bd', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_branch_admin_cannot_sign_in_to_central(): void
    {
        $branch = $this->makeBranch();
        $this->branchUser($branch, 'branch-admin', ['email' => 'admin@test.bd']);

        $this->post($this->centralUrl('login'), ['login' => 'admin@test.bd', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_super_admin_can_sign_in_to_central(): void
    {
        User::factory()->platform()->role('super-admin')->create(['email' => 'root@test.bd']);

        $this->post($this->centralUrl('login'), ['login' => 'root@test.bd', 'password' => 'password'])
            ->assertRedirectContains('/super-admin');
        $this->assertSame('central', session('tenant_key'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $branch = $this->makeBranch();

        $this->get($this->branchUrl($branch, 'dashboard'))->assertRedirectContains('/login');
    }

    public function test_logout_ends_session(): void
    {
        $branch = $this->makeBranch();
        $user = $this->branchUser($branch);

        $this->actingOnBranch($user, $branch)->post($this->branchUrl($branch, 'logout'))->assertRedirectContains('/login');
        $this->assertGuest();
    }

    public function test_password_reset_link_is_sent_without_revealing_accounts(): void
    {
        Notification::fake();
        $branch = $this->makeBranch();
        $user = $this->branchUser($branch, 'branch-admin', ['email' => 'reset@test.bd']);

        $this->post($this->branchUrl($branch, 'forgot-password'), ['email' => 'reset@test.bd'])->assertSessionHas('status');
        $this->post($this->branchUrl($branch, 'forgot-password'), ['email' => 'nobody@test.bd'])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
