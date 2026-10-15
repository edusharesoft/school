<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Reports\ExportRun;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** "My exports": the background exports of the signed-in user (nobody else's), download and delete. */
class ExportRunController extends Controller
{
    public function __construct(protected ExportService $exports, protected AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('reports.view');

        return view('reports.exports', [
            'layout' => tenant()->isCentral() ? 'layouts.central' : 'layouts.branch', 'prefix' => tenant()->isCentral() ? 'central.reports' : 'branch.reports',
            'runs' => $this->mine()->latest('id')->limit(100)->get(), 'keepDays' => (int) config('reports.keep_days', 7),
        ]);
    }

    public function download(ExportRun $run): StreamedResponse
    {
        $this->authorize('reports.view');
        $run = $this->own($run);
        abort_unless($run->isDownloadable() && Storage::disk((string) config('reports.disk', 'local'))->exists($run->file_path), 404);

        $run->increment('downloads');
        $this->audit->log('report.export_downloaded', $run, [], ['report' => $run->report_key, 'format' => $run->format]);

        return Storage::disk((string) config('reports.disk', 'local'))->download($run->file_path, $run->file_name, ['Content-Type' => $run->mime(), 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(ExportRun $run): RedirectResponse
    {
        $this->authorize('reports.view');
        $run = $this->own($run);
        if ($run->isPending()) {
            return back()->with('error', __('This export is still being prepared.'));
        }
        $this->exports->deleteFile($run);
        $run->delete();

        return back()->with('success', __('Export deleted.'));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ExportRun> only the user's own exports of the current area */
    protected function mine()
    {
        return ExportRun::query()->ownedBy(Auth::user())
            ->when(tenant()->isCentral(), fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', tenant()->branchId()));
    }

    protected function own(ExportRun $run): ExportRun
    {
        return $this->mine()->whereKey($run->id)->firstOrFail();
    }
}
