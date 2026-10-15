<?php

namespace App\Models\Saas;

use App\Models\Concerns\Auditable;
use App\Models\Institution;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The subscription of one institution. The stored status is what the platform owner decided (trial / active /
 * suspended / cancelled); phase() adds the calendar: a trial or paid period that has run out gets a grace period
 * (everything still works, a warning is shown) and then becomes read-only ("expired").
 */
class Subscription extends Model
{
    use Auditable;

    public const STATUSES = ['trial', 'active', 'suspended', 'cancelled'];

    public const CYCLES = ['monthly', 'yearly'];

    protected $fillable = ['institution_id', 'plan_id', 'status', 'billing_cycle', 'trial_ends_on', 'period_start', 'period_end', 'cancelled_at', 'notes'];

    protected function casts(): array
    {
        return ['trial_ends_on' => 'date', 'period_start' => 'date', 'period_end' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->latest('paid_on')->latest('id');
    }

    /** Last day of the current paid / trial period. */
    public function endsOn(): ?CarbonImmutable
    {
        $d = $this->status === 'trial' ? $this->trial_ends_on : $this->period_end;

        return $d ? CarbonImmutable::parse($d->toDateString()) : null;
    }

    /** trial | active | grace | expired | suspended */
    public function phase(?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::today();
        if ($this->status === 'suspended') {
            return 'suspended';
        }
        $end = $this->endsOn();
        if ($end === null) {
            return $this->status === 'trial' ? 'trial' : 'active';
        }
        if ($today->lessThanOrEqualTo($end)) {
            return $this->status === 'trial' ? 'trial' : 'active';
        }

        return $today->lessThanOrEqualTo($end->addDays((int) ($this->plan?->grace_days ?? 7))) ? 'grace' : 'expired';
    }

    /** Writes are allowed while trial / active / grace. */
    public function isWritable(?CarbonImmutable $today = null): bool
    {
        return in_array($this->phase($today), ['trial', 'active', 'grace'], true);
    }

    public function daysLeft(?CarbonImmutable $today = null): ?int
    {
        $end = $this->endsOn();

        return $end ? (int) ($today ?? CarbonImmutable::today())->diffInDays($end, false) : null;
    }
}
