<?php

namespace App\Console\Commands;

use App\Models\Saas\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupVerifyCommand extends Command
{
    protected $signature = 'backup:verify {id? : Backup number (default: the newest good one)} {--drill : Also do a test restore into a scratch database} {--connection= : Scratch database connection for the drill (default: a temporary SQLite file)}';

    protected $description = 'Check a backup file; with --drill restore it into a scratch database and compare the row counts (the restore drill)';

    public function handle(BackupService $backups): int
    {
        $run = $this->argument('id') ? BackupRun::query()->find($this->argument('id')) : $backups->latestGood();
        if (! $run) {
            $this->error('There is no backup to check.');

            return self::FAILURE;
        }
        $this->info("Checking backup #{$run->id} ({$run->file_path})".($this->option('drill') ? ' with a restore drill …' : ' …'));
        $result = $backups->verify($run, (bool) $this->option('drill'), $this->option('connection') ?: null);
        $this->line(implode("\n", $result['notes']));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
