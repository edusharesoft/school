<?php

namespace App\Console\Commands;

use App\Services\Library\LibraryService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Release reservations nobody collected and send overdue reminders: php artisan tenancy:run "library:housekeeping" */
class LibraryHousekeeping extends Command
{
    protected $signature = 'library:housekeeping';

    protected $description = 'Expire uncollected reservations and send overdue-book reminders for the current branch';

    public function handle(TenantContext $tenant, LibraryService $library): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run inside a branch context: php artisan tenancy:run "library:housekeeping"');

            return self::FAILURE;
        }
        $this->line(sprintf('  %d reservation(s) expired, %d overdue reminder(s) queued.', $library->expireReservations(), $library->sendOverdueReminders()));

        return self::SUCCESS;
    }
}
