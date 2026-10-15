<?php

namespace App\Console\Commands;

use App\Services\Communication\FeeReminderService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Weekly-at-most outstanding fee reminders to guardians: php artisan tenancy:run "notifications:fee-reminders" */
class NotificationsFeeReminders extends Command
{
    protected $signature = 'notifications:fee-reminders';

    protected $description = 'Remind guardians about overdue fees (current branch; off unless enabled in notification settings)';

    public function handle(TenantContext $tenant, FeeReminderService $reminders): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run inside a branch context: php artisan tenancy:run "notifications:fee-reminders"');

            return self::FAILURE;
        }
        $this->line(sprintf('  %d reminder message(s) created.', $reminders->run()));

        return self::SUCCESS;
    }
}
