<?php

namespace App\Console\Commands;

use App\Services\Reports\ExportService;
use Illuminate\Console\Command;

/** Delete finished report exports past their keep-days and fail exports stuck for hours: php artisan reports:housekeeping */
class ReportsHousekeeping extends Command
{
    protected $signature = 'reports:housekeeping';

    protected $description = 'Delete expired background report exports';

    public function handle(ExportService $exports): int
    {
        $this->line(sprintf('  %d export file(s) removed.', $exports->purge()));

        return self::SUCCESS;
    }
}
