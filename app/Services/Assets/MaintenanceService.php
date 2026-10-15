<?php

namespace App\Services\Assets;

use App\Models\Assets\Asset;
use App\Models\Assets\MaintenanceLog;
use App\Models\Assets\MaintenancePlan;
use App\Models\User;
use App\Services\Assets\Concerns\AssetHelpers;
use App\Services\Finance\LedgerService;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintenance, calibration and repairs. A plan repeats every N months; completing a job done for a plan moves its next due date
 * N months after the day the work was actually done. Costs are recorded for reporting and are NOT posted to the books (record the
 * bill as an expense in Fees & accounts if it should appear there).
 */
class MaintenanceService
{
    use AssetHelpers;

    public function __construct(
        protected SequenceService $sequences,
        protected TenantContext $tenant,
        protected NotificationHub $hub,
        protected RecipientResolver $recipients,
        protected LedgerService $ledger,
    ) {}

    // ───────────────────────────── plans ─────────────────────────────

    /** @param array<string,mixed> $data */
    public function createPlan(Asset $asset, array $data): MaintenancePlan
    {
        $this->assertLive($asset);
        $plan = new MaintenancePlan($this->planAttributes($data));
        $start = CarbonImmutable::parse($data['start_on'] ?? now())->startOfDay();
        $last = ! empty($data['last_done_on']) ? CarbonImmutable::parse($data['last_done_on'])->startOfDay() : null;
        $plan->forceFill(['asset_id' => $asset->id, 'start_on' => $start->toDateString(), 'last_done_on' => $last?->toDateString(),
            'next_due_on' => ($last ? $last->addMonthsNoOverflow($plan->interval_months) : $start)->toDateString(), 'is_active' => true])->save();

        return $plan;
    }

    public function updatePlan(MaintenancePlan $plan, array $data): MaintenancePlan
    {
        $attrs = $this->planAttributes($data + ['kind' => $plan->kind, 'title' => $plan->title, 'interval_months' => $plan->interval_months]);
        $plan->fill($attrs);
        $plan->is_active = (bool) ($data['is_active'] ?? $plan->is_active);
        if (! empty($data['next_due_on'])) {
            $plan->next_due_on = CarbonImmutable::parse($data['next_due_on'])->startOfDay()->toDateString();
        } elseif ($plan->isDirty('interval_months') && $plan->last_done_on) {
            $plan->next_due_on = $plan->last_done_on->addMonthsNoOverflow($plan->interval_months)->toDateString();
        }
        $plan->save();

        return $plan;
    }

    /** Tick a plan off: records a completed job of the plan's kind and moves the due date on. */
    public function markPlanDone(MaintenancePlan $plan, array $data, User $user): MaintenanceLog
    {
        $this->assertLive($plan->asset);
        $data += ['completed_on' => now()->toDateString()];

        return DB::transaction(function () use ($plan, $data, $user) {
            $log = $this->createLog($plan->asset, ['kind' => $plan->kind, 'title' => $plan->title, 'reported_on' => $data['completed_on'], 'employee_id' => $plan->employee_id,
                'vendor_id' => $data['vendor_id'] ?? $plan->vendor_id, 'asset_maintenance_plan_id' => $plan->id], $user);

            return $this->complete($log, $data, $user);
        });
    }

    // ───────────────────────────── work orders ─────────────────────────────

    /** @param array<string,mixed> $data */
    public function createLog(Asset $asset, array $data, User $user): MaintenanceLog
    {
        $this->assertLive($asset);
        $kind = $data['kind'] ?? 'repair';
        if (! in_array($kind, MaintenanceLog::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('Choose the kind of work.')]);
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => __('Say what needs doing.')]);
        }
        $reported = CarbonImmutable::parse($data['reported_on'] ?? now())->startOfDay();
        if ($reported->isFuture()) {
            throw ValidationException::withMessages(['reported_on' => __('The reported date cannot be in the future.')]);
        }

        return DB::transaction(function () use ($asset, $data, $user, $kind, $title, $reported) {
            $log = new MaintenanceLog(['kind' => $kind, 'title' => mb_substr($title, 0, 150), 'description' => $this->clip($data['description'] ?? null, 2000),
                'reported_on' => $reported->toDateString(), 'employee_id' => $data['employee_id'] ?? null, 'vendor_id' => $data['vendor_id'] ?? null]);
            $log->forceFill(['log_no' => $this->docNo('WO', $reported), 'asset_id' => $asset->id, 'asset_maintenance_plan_id' => $data['asset_maintenance_plan_id'] ?? null,
                'status' => 'open', 'takes_offline' => (bool) ($data['takes_offline'] ?? $kind === 'repair'), 'created_by' => $user->id])->save();
            $this->refreshOffline($asset);

            return $log;
        });
    }

    public function start(MaintenanceLog $log, ?string $date = null): MaintenanceLog
    {
        return DB::transaction(function () use ($log, $date) {
            $log = MaintenanceLog::query()->whereKey($log->id)->lockForUpdate()->firstOrFail();
            if ($log->status !== 'open') {
                throw ValidationException::withMessages(['status' => __('Only an open work order can be started.')]);
            }
            $log->forceFill(['status' => 'in_progress', 'started_on' => CarbonImmutable::parse($date ?? now())->toDateString()])->save();

            return $log;
        });
    }

    /** @param array{completed_on?:?string, cost?:mixed, outcome?:?string, reference?:?string, condition_after?:?string, vendor_id?:mixed} $data */
    public function complete(MaintenanceLog $log, array $data, User $user): MaintenanceLog
    {
        $done = CarbonImmutable::parse($data['completed_on'] ?? now())->startOfDay();
        $cost = round(max(0.0, (float) ($data['cost'] ?? 0)), 2);
        $condition = $data['condition_after'] ?? null;
        if ($condition !== null && $condition !== '' && ! in_array($condition, Asset::CONDITIONS, true)) {
            throw ValidationException::withMessages(['condition_after' => __('Choose a condition.')]);
        }

        return DB::transaction(function () use ($log, $data, $done, $cost, $condition) {
            $log = MaintenanceLog::query()->whereKey($log->id)->lockForUpdate()->firstOrFail();
            if (! $log->isOpen()) {
                throw ValidationException::withMessages(['status' => __('This work order is already closed.')]);
            }
            if ($done->isFuture() || $done->lt($log->reported_on)) {
                throw ValidationException::withMessages(['completed_on' => __('The completion date must be between the reported date and today.')]);
            }
            $log->forceFill(['status' => 'completed', 'completed_on' => $done->toDateString(), 'started_on' => $log->started_on ?? $done->toDateString(), 'cost' => $cost,
                'outcome' => $this->clip($data['outcome'] ?? null), 'reference' => $this->clip($data['reference'] ?? null, 100),
                'vendor_id' => $data['vendor_id'] ?? $log->vendor_id, 'condition_after' => $condition ?: null])->save();

            $asset = Asset::query()->whereKey($log->asset_id)->lockForUpdate()->first();
            if ($asset && $condition) {
                $asset->forceFill(['condition' => $condition])->save();
            }
            if ($log->asset_maintenance_plan_id && ($plan = MaintenancePlan::query()->lockForUpdate()->find($log->asset_maintenance_plan_id))) {
                // The next job is due N months after the work was done — never earlier than it already was after a late job.
                $plan->forceFill(['last_done_on' => $done->toDateString(), 'next_due_on' => $done->addMonthsNoOverflow($plan->interval_months)->toDateString(), 'last_notified_on' => null])->save();
            }
            if ($asset) {
                $this->refreshOffline($asset);
            }

            return $log;
        });
    }

    public function cancel(MaintenanceLog $log, string $reason): MaintenanceLog
    {
        return DB::transaction(function () use ($log, $reason) {
            $log = MaintenanceLog::query()->whereKey($log->id)->lockForUpdate()->firstOrFail();
            if (! $log->isOpen()) {
                throw ValidationException::withMessages(['status' => __('This work order is already closed.')]);
            }
            $log->forceFill(['status' => 'cancelled', 'outcome' => $this->clip($reason)])->save();
            if ($asset = Asset::query()->lockForUpdate()->find($log->asset_id)) {
                $this->refreshOffline($asset);
            }

            return $log;
        });
    }

    /** An asset is "under repair" while any open work order of it takes it out of service. */
    public function refreshOffline(Asset $asset): void
    {
        $offline = $asset->logs()->whereIn('status', MaintenanceLog::OPEN)->where('takes_offline', true)->exists();
        if ($asset->is_under_repair !== $offline) {
            $asset->forceFill(['is_under_repair' => $offline])->save();
        }
    }

    // ───────────────────────────── reminders ─────────────────────────────

    /**
     * Tell the responsible employee about plans due within $lead days (or overdue), at most once a week per plan.
     *
     * @return int notifications created
     */
    public function sendDueReminders(?CarbonImmutable $today = null, int $lead = 7): int
    {
        $today ??= CarbonImmutable::today();
        $sent = 0;
        $plans = MaintenancePlan::query()->open()->whereNotNull('employee_id')->whereDate('next_due_on', '<=', $today->addDays($lead)->toDateString())
            ->where(fn ($q) => $q->whereNull('last_notified_on')->orWhereDate('last_notified_on', '<=', $today->subDays(7)->toDateString()))
            ->with(['asset:id,code,name,name_bn', 'employee'])->get();

        foreach ($plans as $plan) {
            if (! $plan->employee || ! $plan->asset) {
                continue;
            }
            $days = (int) $today->diffInDays($plan->next_due_on->startOfDay(), false);
            $vars = ['asset' => $plan->asset->displayName().' ('.$plan->asset->code.')', 'task' => $plan->title, 'due_date' => $plan->next_due_on->format('d/m/Y'),
                'when' => $days < 0 ? __(':n day(s) overdue', ['n' => abs($days)]) : ($days === 0 ? __('due today') : __('due in :n day(s)', ['n' => $days]))];
            try {
                $stats = $this->hub->notify('assets.maintenance_due', [$this->recipients->employee($plan->employee)->with($vars)], $vars, $plan, ['dedupe_suffix' => $today->toDateString()]);
                $sent += $stats['created'];
            } catch (\Throwable $e) {
                report($e);
            }
            $plan->forceFill(['last_notified_on' => $today->toDateString()])->save();
        }

        return $sent;
    }

    // ───────────────────────────── helpers ─────────────────────────────

    protected function assertLive(Asset $asset): void
    {
        if (! $asset->isLive()) {
            throw ValidationException::withMessages(['asset' => __('A disposed or cancelled asset cannot be maintained.')]);
        }
    }

    protected function planAttributes(array $data): array
    {
        $kind = $data['kind'] ?? 'preventive';
        if (! in_array($kind, MaintenancePlan::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('Choose the kind of task.')]);
        }
        $interval = (int) ($data['interval_months'] ?? 0);
        if ($interval < 1 || $interval > 120) {
            throw ValidationException::withMessages(['interval_months' => __('Repeat every 1 to 120 months.')]);
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => __('Name the task.')]);
        }

        return ['title' => mb_substr($title, 0, 150), 'kind' => $kind, 'interval_months' => $interval, 'employee_id' => $data['employee_id'] ?? null,
            'vendor_id' => $data['vendor_id'] ?? null, 'note' => $this->clip($data['note'] ?? null)];
    }
}
