<?php

namespace App\Console\Commands;

use App\Services\Communication\NoticeService;
use App\Services\Notifications\NotificationHub;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Every minute, per branch: publish scheduled notices that are due, release scheduled messages and re-queue stuck ones.
 *   php artisan tenancy:run "notifications:dispatch"
 */
class NotificationsDispatch extends Command
{
    protected $signature = 'notifications:dispatch';

    protected $description = 'Publish due notices and send due / stuck messages of the current branch';

    public function handle(TenantContext $tenant, NotificationHub $hub, NoticeService $notices): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run inside a branch context: php artisan tenancy:run "notifications:dispatch"');

            return self::FAILURE;
        }
        $published = $notices->publishDue();
        $queued = $hub->dispatchDue();
        $synced = $hub->syncSmsDelivery();
        if ($published || $queued || $synced) {
            $this->line(sprintf('  %d notice(s) published, %d message(s) queued, %d SMS status update(s).', $published, $queued, $synced));
        }

        return self::SUCCESS;
    }
}
