<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use App\Services\Finance\ConsolidatedReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consolidated institutional finance: every branch side by side and in total. */
class FinanceReportController extends Controller
{
    public function index(Request $request, ConsolidatedReportService $reports, DashboardService $dashboard): View
    {
        $this->authorize('financial-reports.view');
        $from = $request->date('from') ? CarbonImmutable::parse($request->date('from')) : CarbonImmutable::today()->startOfYear();
        $to = $request->date('to') ? CarbonImmutable::parse($request->date('to')) : CarbonImmutable::today();

        return view('central.finance.index', $reports->summary($dashboard->visibleBranchIds(), $from->startOfDay(), $to->startOfDay()) + ['from' => $from, 'to' => $to]);
    }
}
