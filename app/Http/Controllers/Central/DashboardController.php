<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->dashboard->range($filters);

        return view('central.dashboard', [
            'branches' => Branch::query()->visibleTo($request->user())->ordered()->get(['id', 'name', 'subdomain', 'status', 'institution_id']),
            'filters' => array_merge($filters, ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->central($this->filters($request)));
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->dashboard->branchSummaryRows($this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Bangla correctly
            fputcsv($out, array_keys($rows->first() ?? ['branch' => null]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'branch-summary-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Validated filters; branch filter restricted to branches visible in this context. */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if (! empty($validated['branch_id']) && ! in_array((int) $validated['branch_id'], $this->dashboard->visibleBranchIds(), true)) {
            abort(404);
        }

        return $validated;
    }
}
