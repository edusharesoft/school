<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run {--verify : Check the new file right away} {--trigger=manual}';

    protected $description = 'Write a full backup (database tables + uploaded files) to storage/app/private/backups and apply the retention rules';

    public function handle(BackupService $backups): int
    {
        $run = $backups->run((string) $this->option('trigger'));
        if ($run->status !== 'done') {
            $this->error('Backup failed: '.$run->error);

            return self::FAILURE;
        }
        $this->info(sprintf('Backup #%d written: %s (%s MB, %d tables, %s rows, %d files)', $run->id, $run->file_path, number_format($run->bytes / 1048576, 2), $run->tables_count, number_format($run->rows_count), $run->files_count));
        if ($run->error) {
            $this->warn($run->error);
        }
        if ($this->option('verify')) {
            $result = $backups->verify($run);
            $this->line(implode("\n", $result['notes']));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
