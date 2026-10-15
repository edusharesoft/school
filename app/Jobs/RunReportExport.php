<?php

namespace App\Jobs;

use App\Jobs\Concerns\TenantAware;
use App\Models\Reports\ExportRun;
use App\Services\Reports\ExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Builds the file of one export_runs row in the tenant the request was made in. Failures are recorded on the row, not retried blindly. */
class RunReportExport implements ShouldQueue
{
    use Queueable, TenantAware;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $runId)
    {
        $this->captureTenant();
    }

    public function handle(ExportService $exports): void
    {
        $run = ExportRun::query()->find($this->runId);
        if ($run) {
            $exports->execute($run);
        }
    }

    public function failed(?\Throwable $e): void
    {
        ExportRun::query()->whereKey($this->runId)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'error' => mb_substr((string) $e?->getMessage(), 0, 480), 'finished_at' => now()]);
    }
}
