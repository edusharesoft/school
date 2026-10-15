<?php

namespace App\Console\Commands;

use App\Services\Assets\MaintenanceService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Remind people about maintenance that is coming due: php artisan tenancy:run "assets:housekeeping" */
class AssetsHousekeeping extends Command
{
    protected $signature = 'assets:housekeeping';

    protected $description = 'Send maintenance-due reminders for the current branch';

    public function handle(TenantContext $tenant, MaintenanceService $maintenance): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run inside a branch context: php artisan tenancy:run "assets:housekeeping"');

            return self::FAILURE;
        }
        $this->line(sprintf('  %d maintenance reminder(s) queued.', $maintenance->sendDueReminders()));

        return self::SUCCESS;
    }
}
