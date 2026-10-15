<?php

namespace App\Console\Commands;

use App\Services\Finance\FeeInvoiceService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Charge the configured late fee once on overdue invoices: php artisan tenancy:run "fees:late-fees" */
class FeesLateFees extends Command
{
    protected $signature = 'fees:late-fees';

    protected $description = 'Charge late fees on overdue invoices of the current branch';

    public function handle(TenantContext $tenant, FeeInvoiceService $invoices): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run inside a branch context: php artisan tenancy:run "fees:late-fees"');

            return self::FAILURE;
        }
        $this->line(sprintf('  Late fee charged on %d invoice(s).', $invoices->applyLateFees()));

        return self::SUCCESS;
    }
}
