<?php

namespace App\Console\Commands;

use App\Services\Finance\FinanceSettings;
use App\Services\Hostel\HostelService;
use App\Services\Transport\TransportService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Monthly transport and hostel fee invoices: php artisan tenancy:run "services:generate-fees" (idempotent; follows the branch's "automatic invoices" switch). */
class ServiceFeesGenerate extends Command
{
    protected $signature = 'services:generate-fees {--period= : Month (YYYY-MM), default the current month} {--force : Run even if automatic generation is switched off}';

    protected $description = 'Generate the monthly transport and hostel fee invoices of the current branch';

    public function handle(TenantContext $tenant, FinanceSettings $settings, TransportService $transport, HostelService $hostel): int
    {
        if (! $tenant->isBranch()) {
            $this->error('Run inside a branch context: php artisan tenancy:run "services:generate-fees"');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $settings->get('auto_generate')) {
            $this->line('  Automatic generation is off for this branch — skipped.');

            return self::SUCCESS;
        }
        $period = $this->option('period') ?: CarbonImmutable::today()->format('Y-m');
        $t = $transport->generateFees($period);
        $h = $hostel->generateFees($period);
        $this->line(sprintf('  %s: transport %d created / %d existed; hostel %d created / %d existed.', $period, $t['created'], $t['skipped'], $h['created'], $h['skipped']));

        return self::SUCCESS;
    }
}
