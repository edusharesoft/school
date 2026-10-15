<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryReportService;
use Illuminate\View\View;

/** Inventory home: figures, shortages, latest stock movements. permission: inventory.view */
class InventoryController extends Controller
{
    public function index(InventoryReportService $reports): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.index', $reports->overview());
    }
}
