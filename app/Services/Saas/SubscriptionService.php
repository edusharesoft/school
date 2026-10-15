<?php

namespace App\Services\Saas;

use App\Models\Branch;
use App\Models\Institution;
use App\Models\Saas\Plan;
use App\Models\Saas\Subscription;
use App\Models\Saas\SubscriptionPayment;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plans, trials, manual payments (the platform owner records what was paid) and the limits a plan sets.
 * An institution without a subscription is unlimited, so existing installations keep working until a plan is assigned.
 */
class SubscriptionService
{
    public function __construct(protected AuditLogger $audit) {}

    public function of(?Institution $institution): ?Subscription
    {
        return $institution ? Subscription::query()->with('plan')->where('institution_id', $institution->id)->first() : null;
    }

    /** New registrations get the first active plan that offers a trial (if the platform owner has set one up). */
    public function startDefaultTrial(Institution $institution): ?Subscription
    {
        $plan = Plan::query()->where('is_active', true)->where('trial_days', '>', 0)->orderBy('sort_order')->orderBy('id')->first();

        return $plan ? $this->startTrial($institution, $plan) : null;
    }

    public function startTrial(Institution $institution, Plan $plan, ?int $days = null): Subscription
    {
        $days ??= $plan->trial_days;

        return $this->save($institution, $plan, [
            'status' => 'trial', 'trial_ends_on' => CarbonImmutable::today()->addDays($days)->toDateString(), 'period_start' => null, 'period_end' => null, 'cancelled_at' => null,
        ]);
    }

    /** Create or change the subscription (plan, cycle, dates, notes). */
    public function save(Institution $institution, Plan $plan, array $data): Subscription
    {
        $subscription = Subscription::query()->firstOrNew(['institution_id' => $institution->id]);
        $before = $subscription->exists ? $subscription->only(['plan_id', 'status', 'billing_cycle', 'trial_ends_on', 'period_end']) : [];
        $subscription->fill(array_merge(['billing_cycle' => $subscription->billing_cycle ?: 'monthly'], $data));
        $subscription->plan_id = $plan->id;
        $subscription->save();
        $this->audit->log('subscription.saved', $subscription, $before, $subscription->only(['plan_id', 'status', 'billing_cycle', 'trial_ends_on', 'period_end']));

        return $subscription->load('plan');
    }

    /**
     * Record a payment for $periods billing periods (months or years). The paid period starts when the old one ends
     * (or today when it already ran out) so nobody loses days by paying early.
     */
    public function recordPayment(Subscription $subscription, float $amount, string $method, ?string $reference, int $periods, ?User $actor, ?string $paidOn = null): SubscriptionPayment
    {
        if ($periods < 1 || $periods > 36) {
            throw ValidationException::withMessages(['periods' => __('Choose 1 to 36 periods.')]);
        }

        return DB::transaction(function () use ($subscription, $amount, $method, $reference, $periods, $actor, $paidOn) {
            $today = CarbonImmutable::today();
            $end = $subscription->status === 'active' ? $subscription->endsOn() : null;
            $from = $end && $end->greaterThanOrEqualTo($today) ? $end->addDay() : $today;
            $to = $subscription->billing_cycle === 'yearly' ? $from->addYears($periods)->subDay() : $from->addMonthsNoOverflow($periods)->subDay();

            $payment = SubscriptionPayment::query()->create([
                'subscription_id' => $subscription->id, 'amount' => $amount, 'paid_on' => $paidOn ?: $today->toDateString(), 'method' => $method,
                'reference' => $reference, 'period_from' => $from->toDateString(), 'period_to' => $to->toDateString(), 'recorded_by' => $actor?->id,
            ]);
            $subscription->forceFill([
                'status' => 'active', 'period_start' => $subscription->status === 'active' && $subscription->period_start ? $subscription->period_start : $from->toDateString(),
                'period_end' => $to->toDateString(), 'cancelled_at' => null,
            ])->save();
            $this->audit->log('subscription.payment', $subscription, [], ['amount' => $amount, 'to' => $to->toDateString()]);

            return $payment;
        });
    }

    public function suspend(Subscription $subscription, ?string $note = null): Subscription
    {
        $subscription->forceFill(['status' => 'suspended', 'notes' => $note ?: $subscription->notes])->save();
        $this->audit->log('subscription.suspended', $subscription);

        return $subscription;
    }

    /** Back to active (or trial when it never had a paid period). */
    public function reactivate(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['status' => $subscription->period_end ? 'active' : 'trial', 'cancelled_at' => null])->save();
        $this->audit->log('subscription.reactivated', $subscription);

        return $subscription;
    }

    /** Cancelled subscriptions keep working until the paid period ends, then become read-only. */
    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        $this->audit->log('subscription.cancelled', $subscription);

        return $subscription;
    }

    // ─────────────────────────────── limits ───────────────────────────────

    /** @return array{branches:int, students:int} */
    public function usage(Institution $institution): array
    {
        return [
            'branches' => Branch::query()->where('institution_id', $institution->id)->count(),
            'students' => Student::withoutGlobalScopes()->where('institution_id', $institution->id)->where('status', 'active')->count(),
        ];
    }

    public function assertCanAddBranch(Institution $institution): void
    {
        $limit = $this->of($institution)?->plan?->max_branches;
        if ($limit !== null && $this->usage($institution)['branches'] >= $limit) {
            throw ValidationException::withMessages(['subscription' => __('Your plan allows :n branch(es). Upgrade the plan to add more.', ['n' => $limit])]);
        }
    }

    public function assertCanAddStudents(?int $institutionId, int $adding = 1): void
    {
        $institution = $institutionId ? Institution::query()->find($institutionId) : null;
        $limit = $this->of($institution)?->plan?->max_students;
        if ($limit !== null && $this->usage($institution)['students'] + $adding > $limit) {
            throw ValidationException::withMessages(['subscription' => __('Your plan allows :n active students. Upgrade the plan to admit more.', ['n' => number_format($limit)])]);
        }
    }

    /** Daily: nothing is stored per phase, but expired and about-to-expire subscriptions are written to the audit log once. */
    public function housekeeping(): array
    {
        $out = ['expired' => 0, 'grace' => 0];
        foreach (Subscription::query()->with('plan')->whereIn('status', ['trial', 'active', 'cancelled'])->get() as $subscription) {
            $phase = $subscription->phase();
            if (in_array($phase, ['expired', 'grace'], true)) {
                $out[$phase]++;
            }
        }

        return $out;
    }
}
