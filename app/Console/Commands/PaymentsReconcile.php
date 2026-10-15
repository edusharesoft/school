<?php

namespace App\Console\Commands;

use App\Services\Payments\OnlinePaymentService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Ask the gateways about unfinished online payments (payer closed the browser, lost return, delayed confirmation)
 * and expire abandoned ones: php artisan tenancy:run "payments:reconcile"
 */
class PaymentsReconcile extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Re-verify open online payments of the current branch with their gateways';

    public function handle(TenantContext $tenant, OnlinePaymentService $service): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run inside a branch context: php artisan tenancy:run "payments:reconcile"');

            return self::FAILURE;
        }
        $stats = $service->reconcile();
        if ($stats['checked']) {
            $this->line(sprintf('  %d checked, %d paid, %d expired.', $stats['checked'], $stats['paid'], $stats['expired']));
        }

        return self::SUCCESS;
    }
}
