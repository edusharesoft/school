<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Services\Inventory\InventoryReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Branch inventory reports (PDF / Excel export arrives with the reports centre, part 6e). permission: inventory.view */
class ReportController extends Controller
{
    public function __construct(protected InventoryReportService $reports) {}

    public function stock(Request $request): View
    {
        $this->authorize('inventory.view');
        $filters = $request->only(['q', 'category', 'status']);

        return view('branch.inventory.reports.stock', [
            'items' => $this->reports->stock($filters, 60), 'totals' => $this->reports->stockTotals($filters),
            'byCategory' => $this->reports->valuationByCategory(), 'categories' => Category::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function consumption(Request $request): View
    {
        $this->authorize('inventory.view');
        [$from, $to] = $this->period($request);

        return view('branch.inventory.reports.consumption', $this->reports->consumption($from, $to) + ['from' => $from, 'to' => $to]);
    }

    public function purchases(Request $request): View
    {
        $this->authorize('inventory.view');
        [$from, $to] = $this->period($request);

        return view('branch.inventory.reports.purchases', $this->reports->purchases($from, $to) + ['from' => $from, 'to' => $to]);
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    protected function period(Request $request): array
    {
        $from = $request->date('from') ? CarbonImmutable::parse($request->date('from')) : CarbonImmutable::today()->startOfMonth();
        $to = $request->date('to') ? CarbonImmutable::parse($request->date('to')) : CarbonImmutable::today();

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
