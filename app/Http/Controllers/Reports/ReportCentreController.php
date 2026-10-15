<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Reports\ExportRun;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ExportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The reports centre, used by the branch area and the central area (the report's scope must match the area).
 * Opening needs reports.view plus the report's own permission; exporting needs reports.export (finance reports:
 * financial-reports.export) on top.
 */
class ReportCentreController extends Controller
{
    public function __construct(protected ReportRegistry $registry, protected ExportService $exports, protected AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('reports.view');

        return view('reports.index', $this->shared() + ['groups' => $this->registry->grouped(Auth::user(), $this->scope())]);
    }

    public function show(Request $request, string $report): View
    {
        $this->authorize('reports.view');
        $def = $this->find($report);
        $filters = $def->normalize($request->query());
        $result = $this->exports->build($def, $filters, Auth::user());
        $limit = (int) config('reports.screen_rows', 300);

        return view('reports.show', $this->shared() + [
            'report' => $def, 'filters' => $filters, 'result' => $result->head($limit), 'total' => $result->rowCount(), 'totals' => $result->hasTotals() ? $result->totals() : null,
            'summary' => $result->summary, 'subtitle' => $result->subtitle, 'notes' => $result->notes, 'limit' => $limit,
            'canExport' => $def->canExport(Auth::user()), 'inlineMax' => (int) config('reports.inline_max_rows', 3000),
        ]);
    }

    /** Direct download. A report longer than the inline limit is prepared in the background instead. */
    public function export(Request $request, string $report): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('reports.view');
        $def = $this->find($report);
        abort_unless($def->canExport(Auth::user()), 403);
        $format = $request->validate(['format' => ['required', Rule::in(ExportRun::FORMATS)]])['format'];

        $result = $this->exports->build($def, $request->query(), Auth::user());
        if ($result->rowCount() > (int) config('reports.inline_max_rows', 3000)) {
            $run = $this->exports->queue($def, $request->query(), $format, Auth::user());
            $this->audit->log('report.export_queued', $run, [], ['report' => $def->key(), 'format' => $format, 'rows' => $result->rowCount()]);

            return redirect()->route($this->prefix().'.exports.index')->with('success', __('This report is long, so it is being prepared in the background. You will find it here when it is ready.'));
        }

        $path = $this->exports->tempFile($result, $format, Auth::user());
        $this->audit->log('report.exported', null, [], ['report' => $def->key(), 'format' => $format, 'rows' => $result->rowCount(), 'filters' => $def->normalize($request->query())]);

        return response()->download($path, $this->exports->filename($def, $format), ['Content-Type' => $this->exports->mime($format), 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }

    /** Prepare the export in the background and list it under "My exports". */
    public function queue(Request $request, string $report): RedirectResponse
    {
        $this->authorize('reports.view');
        $def = $this->find($report);
        abort_unless($def->canExport(Auth::user()), 403);
        $format = $request->validate(['format' => ['required', Rule::in(ExportRun::FORMATS)]])['format'];

        $run = $this->exports->queue($def, $request->all(), $format, Auth::user());
        $this->audit->log('report.export_queued', $run, [], ['report' => $def->key(), 'format' => $format]);

        return redirect()->route($this->prefix().'.exports.index')->with('success', __('The export is being prepared. You will find it here when it is ready.'));
    }

    protected function find(string $key): Report
    {
        $report = $this->registry->find($key);
        abort_unless($report && $report->scope() === $this->scope() && $report->canView(Auth::user()), 404);

        return $report;
    }

    protected function scope(): string
    {
        return tenant()->isCentral() ? 'central' : 'branch';
    }

    protected function prefix(): string
    {
        return tenant()->isCentral() ? 'central.reports' : 'branch.reports';
    }

    protected function shared(): array
    {
        return ['layout' => tenant()->isCentral() ? 'layouts.central' : 'layouts.branch', 'prefix' => $this->prefix(), 'registry' => $this->registry];
    }
}
