<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs an artisan command once per branch inside that branch's tenant context.
 *   php artisan tenancy:run "attendance:summarize --date=2026-09-28"            (all active branches)
 *   php artisan tenancy:run "invoices:generate" --branch=3 --branch=4
 */
class TenancyRun extends Command
{
    protected $signature = 'tenancy:run {cmd : The artisan command (quoted, with its options)} {--branch=* : Branch id(s); default all active} {--include-inactive}';

    protected $description = 'Run an artisan command for each branch in its own tenant context';

    public function handle(TenantContext $tenant): int
    {
        $branches = Branch::query()->with('institution')
            ->when($this->option('branch'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when(! $this->option('include-inactive'), fn ($q) => $q->active())
            ->ordered()->get();

        if ($branches->isEmpty()) {
            $this->warn('No matching branches.');

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($branches as $branch) {
            $this->line("<info>▶ [{$branch->id}] {$branch->name}</info>");
            $code = $tenant->runForBranch($branch, fn () => Artisan::call($this->argument('cmd'), [], $this->getOutput()));
            if ($code !== 0) {
                $failures++;
                $this->error("  exit code {$code}");
            }
        }

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
