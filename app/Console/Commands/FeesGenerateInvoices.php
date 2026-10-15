<?php

namespace App\Console\Commands;

use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FeeInvoiceService;
use App\Services\Finance\FinanceSettings;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Monthly fee invoices for the branch in context. Run for every branch through:
 *   php artisan tenancy:run "fees:generate-invoices"
 * Idempotent: a student's invoice for a month is created once (generated_key), so re-runs only fill gaps.
 */
class FeesGenerateInvoices extends Command
{
    protected $signature = 'fees:generate-invoices {--period= : Month (YYYY-MM), default the current month} {--force : Run even if automatic generation is switched off}';

    protected $description = 'Generate the monthly fee invoices of the current branch';

    public function handle(TenantContext $tenant, FeeInvoiceService $invoices, FinanceSettings $settings, ChartOfAccounts $chart): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run inside a branch context: php artisan tenancy:run "fees:generate-invoices"');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $settings->get('auto_generate')) {
            $this->line('  Automatic generation is off for this branch — skipped.');

            return self::SUCCESS;
        }

        $chart->ensure();
        $period = $this->option('period') ?: CarbonImmutable::today()->format('Y-m');
        $stats = $invoices->generateForPeriod($period);
        $this->line(sprintf('  %s: %d created, %d already existed, %d without fees.', $period, $stats['created'], $stats['skipped'], $stats['empty']));

        return self::SUCCESS;
    }
}
