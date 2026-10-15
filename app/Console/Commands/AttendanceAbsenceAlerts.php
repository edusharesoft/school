<?php

namespace App\Console\Commands;

use App\Services\Attendance\AbsenceAlertService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Detect consecutive-absence streaks for the branch in context. Run for every branch through:
 *   php artisan tenancy:run "attendance:absence-alerts"
 * (scheduled daily in routes/console.php). Idempotent — safe to run several times a day.
 */
class AttendanceAbsenceAlerts extends Command
{
    protected $signature = 'attendance:absence-alerts {--date= : Evaluate as of this date (Y-m-d), default today}';

    protected $description = 'Raise consecutive-absence alerts for the current branch';

    public function handle(TenantContext $tenant, AbsenceAlertService $service): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run this command inside a branch context: php artisan tenancy:run "attendance:absence-alerts"');

            return self::FAILURE;
        }

        $date = $this->option('date') ? CarbonImmutable::parse($this->option('date')) : CarbonImmutable::today();
        $stats = $service->detect($date);

        $this->line(sprintf('  %s: %d section(s) checked, %d new alert(s), %d updated.', $date->toDateString(), $stats['sections'], $stats['created'], $stats['updated']));

        return self::SUCCESS;
    }
}
