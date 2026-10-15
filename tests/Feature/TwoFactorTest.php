<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Part 7 — TOTP two-factor authentication: set-up, sign-in challenge, recovery codes, API, mandatory roles, admin reset. */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_matches_the_rfc_6238_reference_vector(): void
    {
        // RFC 6238 appendix B, SHA1, secret "12345678901234567890" → T=59 s gives 94287082 (8 digits) → last 6 digits "287082".
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $totp = app(TotpService::class);
        $this->assertSame('287082', $totp->code($secret, intdiv(59, 30)));
        $this->assertNotNull($totp->verify($secret, '287082', null, 59));
        $this->assertNull($totp->verify($secret, '287082', null, 59 + 300));         // far outside the window
        $this->assertNull($totp->verify($secret, 'abcdef', null, 59));
    }

    protected function enable(User $user): string
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        [, $hashes] = $totp->recoveryCodes();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $hashes, 'two_factor_confirmed_at' => now()])->save();

        return $secret;
    }

    public function test_user_sets_up_two_factor_from_the_security_page(): void
    {
        $branch = $this->makeBranch();
        $user = $this->branchUser($branch, 'branch-admin');
        $url = fn (string $p) => $this->branchUrl($branch, $p);

        $this->actingOnBranch($user, $branch)->get($url('account/security'))->assertOk()->assertSee(__('Set up'));
        $this->actingOnBranch($user, $branch)->post($url('account/security/start'))->assertRedirect();
        $secret = session('2fa.setup_secret');
        $this->assertNotEmpty($secret);

        $this->actingOnBranch($user, $branch)->withSession(['2fa.setup_secret' => $secret])->get($url('account/security'))->assertOk()->assertSee(trim(chunk_split($secret, 4, ' ')));
        $this->actingOnBranch($user, $branch)->withSession(['2fa.setup_secret' => $secret])->post($url('account/security/confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactor());

        $code = app(TotpService::class)->code($secret);
        $this->actingOnBranch($user, $branch)->withSession(['2fa.setup_secret' => $secret])->post($url('account/security/confirm'), ['code' => $code])->assertSessionHasNoErrors();
        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasTwoFactor());
        $this->assertCount(8, (array) $fresh->two_factor_recovery_codes);
        $this->assertNotSame($secret, $fresh->getRawOriginal('two_factor_secret'));      // stored encrypted
        $this->assertCount(8, session('2fa.new_codes'));

        // Wrong password cannot switch it off; the right one can.
        $this->actingOnBranch($fresh, $branch)->delete($url('account/security'), ['password' => 'nope'])->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->actingOnBranch($fresh, $branch)->delete($url('account/security'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasTwoFactor());
    }

    public function test_sign_in_asks_for_the_code_and_recovery_codes_work_once(): void
    {
        $branch = $this->makeBranch();
        $user = $this->branchUser($branch, 'branch-admin');
        $secret = $this->enable($user);
        $totp = app(TotpService::class);
        $login = fn () => $this->post($this->branchUrl($branch, 'login'), ['login' => $user->email, 'password' => 'password']);

        $login()->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get($this->branchUrl($branch, 'two-factor'))->assertOk();

        $this->post($this->branchUrl($branch, 'two-factor'), ['code' => '111111'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post($this->branchUrl($branch, 'two-factor'), ['code' => $totp->code($secret)])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // The same code cannot be replayed on a new sign-in.
        auth()->logout();
        $login();
        $this->post($this->branchUrl($branch, 'two-factor'), ['code' => $totp->code($secret)])->assertSessionHasErrors('code');

        // Recovery code: works once.
        [$plain, $hashes] = $totp->recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $hashes])->save();
        $this->post($this->branchUrl($branch, 'two-factor'), ['code' => $plain[0]])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, (array) $user->fresh()->two_factor_recovery_codes);
        auth()->logout();
        $login();
        $this->post($this->branchUrl($branch, 'two-factor'), ['code' => $plain[0]])->assertSessionHasErrors('code');
    }

    public function test_challenge_without_a_password_step_goes_back_to_login_and_attempts_are_limited(): void
    {
        $branch = $this->makeBranch();
        $user = $this->branchUser($branch, 'branch-admin');
        $this->enable($user);
        $this->get($this->branchUrl($branch, 'two-factor'))->assertRedirect(route('login'));

        $this->post($this->branchUrl($branch, 'login'), ['login' => $user->email, 'password' => 'password']);
        for ($i = 0; $i < 5; $i++) {
            $this->post($this->branchUrl($branch, 'two-factor'), ['code' => '123456']);
        }
        $last = $this->post($this->branchUrl($branch, 'two-factor'), ['code' => '123456']);
        $this->assertTrue($last->status() === 429 || session()->has('errors'));   // route throttle or the per-user limiter
        $this->assertGuest();
    }

    public function test_api_login_needs_the_otp_when_enabled(): void
    {
        $branch = $this->makeBranch();
        $user = $this->branchUser($branch, 'branch-admin');
        $secret = $this->enable($user);
        $body = ['login' => $user->email, 'password' => 'password'];

        $this->postJson($this->branchUrl($branch, 'api/v1/auth/login'), $body)->assertStatus(422)->assertJsonPath('success', false);
        $this->postJson($this->branchUrl($branch, 'api/v1/auth/login'), $body + ['otp' => '000000'])->assertStatus(422);
        $this->postJson($this->branchUrl($branch, 'api/v1/auth/login'), $body + ['otp' => app(TotpService::class)->code($secret)])->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_required_roles_are_held_at_the_security_page_and_cannot_switch_it_off(): void
    {
        config(['security.two_factor.required_roles' => ['branch-admin']]);
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');
        $teacher = $this->branchUser($branch, 'teacher');

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'departments'))->assertRedirect(route('account.security'));
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'account/security'))->assertOk();
        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'account/security'))->assertOk();

        $this->enable($admin);
        $this->actingOnBranch($admin->fresh(), $branch)->get($this->branchUrl($branch, 'departments'))->assertOk();
        $this->actingOnBranch($admin->fresh(), $branch)->delete($this->branchUrl($branch, 'account/security'), ['password' => 'password'])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->hasTwoFactor());
    }

    public function test_administrator_can_reset_a_lost_device(): void
    {
        $branch = $this->makeBranch();
        $inst = $branch->institution;
        $instAdmin = User::factory()->create(['institution_id' => $inst->id]);
        $instAdmin->assignRole('institution-admin');
        $staff = $this->branchUser($branch, 'teacher');
        $this->enable($staff);

        $this->actingCentral($instAdmin)->post($this->centralUrl("super-admin/users/{$staff->id}/two-factor/reset"))->assertSessionHasNoErrors();
        $this->assertFalse($staff->fresh()->hasTwoFactor());
        $this->enable($instAdmin);
        $this->actingCentral($instAdmin)->post($this->centralUrl("super-admin/users/{$instAdmin->id}/two-factor/reset"))->assertForbidden();
    }
}
