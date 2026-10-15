<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institution;
use App\Models\Saas\Plan;
use App\Models\Saas\Subscription;
use App\Models\User;
use App\Services\Saas\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Part 7 — plans, trials, payments, limits and the read-only rule for expired subscriptions. */
class SubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(10, 0));
    }

    protected function plan(array $o = []): Plan
    {
        return Plan::query()->create($o + ['code' => 'basic', 'name' => 'Basic', 'price_monthly' => 1000, 'price_yearly' => 10000, 'max_branches' => 1, 'max_students' => 2, 'trial_days' => 14, 'grace_days' => 5]);
    }

    protected function owner(): User
    {
        $u = User::factory()->create(['institution_id' => null, 'user_type' => 'platform']);
        $u->assignRole('super-admin');

        return $u;
    }

    public function test_phases_follow_the_calendar_and_grace_period(): void
    {
        $branch = $this->makeBranch();
        $svc = app(SubscriptionService::class);
        $sub = $svc->startTrial($branch->institution, $this->plan());
        $this->assertSame('trial', $sub->phase());
        $this->assertTrue($sub->isWritable());
        $this->assertSame(14, $sub->daysLeft());

        $this->travelTo(now()->addDays(16));
        $this->assertSame('grace', $sub->fresh('plan')->phase());
        $this->assertTrue($sub->fresh('plan')->isWritable());
        $this->travelTo(now()->addDays(5));
        $this->assertSame('expired', $sub->fresh('plan')->phase());
        $this->assertFalse($sub->fresh('plan')->isWritable());
    }

    public function test_payment_extends_from_the_end_of_the_paid_period_and_suspension_blocks(): void
    {
        $branch = $this->makeBranch();
        $svc = app(SubscriptionService::class);
        $sub = $svc->startTrial($branch->institution, $this->plan());
        $svc->recordPayment($sub, 1000, 'bank', 'TX1', 1, null);
        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame('2026-07-19', $sub->period_end->toDateString());          // 20 Jun + 1 month - 1 day

        $svc->recordPayment($sub, 1000, 'cash', null, 2, null);                       // paying early keeps the days
        $this->assertSame('2026-09-19', $sub->fresh()->period_end->toDateString());
        $this->assertCount(2, $sub->payments);

        $svc->suspend($sub->fresh('plan'));
        $this->assertSame('suspended', $sub->fresh('plan')->phase());
        $svc->reactivate($sub->fresh('plan'));
        $this->assertSame('active', $sub->fresh('plan')->phase());
    }

    public function test_plan_limits_stop_new_branches_and_students(): void
    {
        $branch = $this->makeBranch();
        $inst = $branch->institution;
        $setup = $this->academicSetup($branch);
        $this->enrolStudent($branch, $setup, 'One');
        app(SubscriptionService::class)->startTrial($inst, $this->plan(['max_students' => 2, 'max_branches' => 1]));

        $this->enrolStudent($branch, $setup, 'Two');                                   // 2 of 2
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            $this->enrolStudent($branch, $setup, 'Three');
        } finally {
            $this->assertSame(2, app(SubscriptionService::class)->usage($inst)['students']);
        }
    }

    public function test_branch_limit_and_unlimited_without_subscription(): void
    {
        $branch = $this->makeBranch();
        $inst = $branch->institution;
        $svc = app(SubscriptionService::class);
        $svc->assertCanAddBranch($inst);                                              // no subscription → unlimited
        $svc->startTrial($inst, $this->plan(['max_branches' => 1]));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->assertCanAddBranch($inst);
    }

    public function test_expired_institution_can_read_but_not_write(): void
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');
        $sub = app(SubscriptionService::class)->startTrial($branch->institution, $this->plan());

        $post = fn () => $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'departments'), ['name' => 'Science '.random_int(1, 9999), 'code' => 'S'.random_int(100, 999), 'is_active' => 1]);
        $post()->assertSessionHasNoErrors();

        $this->travelTo(now()->addDays(30));
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'departments'))->assertOk();
        $post()->assertSessionHas('error');
        $this->assertSame(1, \App\Models\Department::withoutGlobalScopes()->where('branch_id', $branch->id)->count());

        app(SubscriptionService::class)->recordPayment($sub->fresh('plan'), 1000, 'bank', null, 1, null);
        $post()->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\Department::withoutGlobalScopes()->where('branch_id', $branch->id)->count());
    }

    public function test_owner_manages_plans_and_subscriptions_but_institution_admin_cannot(): void
    {
        $owner = $this->owner();
        $branch = $this->makeBranch();
        $inst = $branch->institution;
        $c = fn (string $p) => $this->centralUrl($p);

        $this->actingCentral($owner)->post($c('super-admin/plans'), ['code' => 'pro', 'name' => 'Pro', 'price_monthly' => 2500, 'price_yearly' => 25000, 'max_branches' => 3, 'max_students' => '', 'trial_days' => 14, 'grace_days' => 7, 'is_active' => 1])->assertSessionHasNoErrors();
        $plan = Plan::query()->where('code', 'pro')->sole();
        $this->assertNull($plan->max_students);
        $this->actingCentral($owner)->post($c('super-admin/plans'), ['code' => 'pro', 'name' => 'Dup', 'price_monthly' => 1, 'price_yearly' => 1, 'trial_days' => 0, 'grace_days' => 0])->assertSessionHasErrors('code');

        $this->actingCentral($owner)->put($c("super-admin/subscriptions/{$inst->id}"), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])->assertSessionHasNoErrors();
        $this->assertSame('trial', Subscription::query()->sole()->status);
        $this->actingCentral($owner)->post($c("super-admin/subscriptions/{$inst->id}/payments"), ['amount' => 2500, 'method' => 'bank', 'periods' => 1])->assertSessionHasNoErrors();
        $this->assertSame('active', Subscription::query()->sole()->status);
        $this->actingCentral($owner)->get($c('super-admin/subscriptions'))->assertOk()->assertSee($inst->name);
        $this->actingCentral($owner)->get($c("super-admin/subscriptions/{$inst->id}"))->assertOk()->assertSee('Pro');
        $this->actingCentral($owner)->post($c("super-admin/subscriptions/{$inst->id}/suspend"))->assertSessionHasNoErrors();
        $this->assertSame('suspended', Subscription::query()->sole()->status);
        $this->actingCentral($owner)->delete($c("super-admin/plans/{$plan->id}"))->assertSessionHas('warning');   // in use → switched off
        $this->assertFalse($plan->fresh()->is_active);

        $instAdmin = User::factory()->create(['institution_id' => $inst->id]);
        $instAdmin->assignRole('institution-admin');
        $this->actingCentral($instAdmin)->get($c('super-admin/subscriptions'))->assertForbidden();
        $this->actingCentral($instAdmin)->get($c('super-admin/plans'))->assertForbidden();
    }

    public function test_registration_starts_a_trial_when_a_trial_plan_exists(): void
    {
        $this->plan();
        [$inst] = app(\App\Services\Registration\InstitutionRegistrationService::class)->register([
            'institution_name' => 'New School', 'subdomain' => 'newschool', 'email' => 'new@example.com', 'mobile' => '01712345678',
            'address' => 'Dhaka', 'name' => 'Owner', 'password' => 'secret-pass-123',
        ]);
        $sub = app(SubscriptionService::class)->of($inst);
        $this->assertSame('trial', $sub->status);
        $this->assertSame(14, $sub->daysLeft());
    }
}
