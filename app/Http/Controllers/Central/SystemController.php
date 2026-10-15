<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackupJob;
use App\Models\Saas\BackupRun;
use App\Services\Backup\BackupService;
use App\Services\Monitoring\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Platform operations: backups, restore drills and system health. Only the platform owner (backups hold every institution's data). */
class SystemController extends Controller
{
    public function __construct(protected BackupService $backups, protected HealthService $health) {}

    protected function owner(Request $request, string $permission): void
    {
        abort_unless($request->user()->isPlatformOwner(), 403);
        $this->authorize($permission);
    }

    public function health(Request $request): View
    {
        $this->owner($request, 'backups.view');

        return view('central.system.health', ['result' => $this->health->run()]);
    }

    public function backups(Request $request): View
    {
        $this->owner($request, 'backups.view');

        return view('central.system.backups', ['runs' => BackupRun::query()->with('creator:id,name')->latest('id')->paginate(20), 'passphrase' => (string) config('backup.passphrase') !== '']);
    }

    public function run(Request $request): RedirectResponse
    {
        $this->owner($request, 'backups.run');
        RunBackupJob::dispatch('run', $request->user()->id);

        return back()->with('success', config('queue.default') === 'sync' ? __('Backup finished.') : __('The backup is being made in the background. Refresh in a minute.'));
    }

    public function verify(Request $request, BackupRun $backup): RedirectResponse
    {
        $this->owner($request, 'backups.run');
        RunBackupJob::dispatch('verify', $request->user()->id, $backup->id, $request->boolean('drill'));

        return back()->with('success', config('queue.default') === 'sync' ? __('Check finished.') : __('The check is running in the background. Refresh in a minute.'));
    }

    public function download(Request $request, BackupRun $backup): StreamedResponse|RedirectResponse
    {
        $this->owner($request, 'backups.run');
        $path = $this->backups->path($backup);
        if (! $path) {
            return back()->with('error', __('The backup file is missing.'));
        }

        return response()->streamDownload(fn () => readfile($path), basename($path), ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, BackupRun $backup): RedirectResponse
    {
        $this->owner($request, 'backups.run');
        $this->backups->delete($backup);

        return back()->with('success', __('Backup deleted.'));
    }
}
