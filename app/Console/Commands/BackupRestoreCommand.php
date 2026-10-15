<?php

namespace App\Console\Commands;

use App\Models\Saas\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore {source : Backup number, or the path of a backup .zip (disaster recovery)} {--force : Really overwrite the current data} {--no-files : Restore the database only}';

    protected $description = 'DESTRUCTIVE: replace the current data with the contents of a backup (run the migrations first on an empty database)';

    public function handle(BackupService $backups): int
    {
        $source = (string) $this->argument('source');
        try {
            $run = ctype_digit($source) ? BackupRun::query()->find($source) : ($this->option('force') ? $backups->adopt($source) : (is_file($source) ? new BackupRun(['id' => 0, 'file_path' => $source]) : null));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if (! $run) {
            $this->error('No such backup.');

            return self::FAILURE;
        }
        if (! $this->option('force')) {
            $this->warn("This empties every table found in backup #{$run->id} and loads the backup instead. Run again with --force to do it.");

            return self::FAILURE;
        }
        if (app()->isProduction() && ! $this->confirm('This is PRODUCTION. Current data will be lost. Continue?')) {
            return self::FAILURE;
        }
        try {
            $r = $backups->restore($run, ! $this->option('no-files'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Restored {$r['rows']} rows in {$r['tables']} tables and {$r['files']} files. Now run: php artisan optimize:clear");

        return self::SUCCESS;
    }
}
