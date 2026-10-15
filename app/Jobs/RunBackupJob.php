<?php

namespace App\Jobs;

use App\Models\Saas\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** "Run backup now" / "Verify" from the Backups page (not tenant-bound: backups cover the whole platform). */
class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public string $action, public ?int $userId = null, public ?int $runId = null, public bool $drill = false) {}

    public function handle(BackupService $backups): void
    {
        if ($this->action === 'run') {
            $backups->run('manual', $this->userId);

            return;
        }
        if ($run = BackupRun::query()->find($this->runId)) {
            $backups->verify($run, $this->drill);
        }
    }
}
