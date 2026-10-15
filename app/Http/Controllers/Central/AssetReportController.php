<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\Assets\AssetReportService;
use App\Services\Dashboard\DashboardService;
use Illuminate\View\View;

/** Branch-wise assets: cost, book value, repairs, overdue maintenance and disposals of every branch side by side. */
class AssetReportController extends Controller
{
    public function index(AssetReportService $reports, DashboardService $dashboard): View
    {
        $this->authorize('assets.view');

        return view('central.assets.index', $reports->consolidated($dashboard->visibleBranchIds()));
    }
}
