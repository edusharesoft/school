<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use App\Services\Inventory\InventoryReportService;
use Illuminate\View\View;

/** Branch-wise inventory: stock value, shortages, open orders and supplier dues of every branch side by side. */
class InventoryReportController extends Controller
{
    public function index(InventoryReportService $reports, DashboardService $dashboard): View
    {
        $this->authorize('inventory.view');

        return view('central.inventory.index', $reports->consolidated($dashboard->visibleBranchIds()));
    }
}
