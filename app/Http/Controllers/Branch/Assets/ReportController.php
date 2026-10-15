<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Services\Assets\AssetReportService;
use App\Services\Finance\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Asset reports (PDF / Excel export arrives with the reports centre, part 6e; the register itself exports to CSV). permission: assets.view */
class ReportController extends Controller
{
    public function __construct(protected AssetReportService $reports) {}

    public function summary(): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.reports.summary', ['byCategory' => $this->reports->byCategory(), 'books' => $this->reports->bookCheck()]);
    }

    public function depreciation(Request $request, LedgerService $ledger): View
    {
        $this->authorize('assets.view');
        $year = $ledger->yearOf(now());
        $valid = fn ($p) => is_string($p) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p);
        $from = $valid($request->input('from')) ? $request->input('from') : $year->starts_on->format('Y-m');
        $to = $valid($request->input('to')) ? $request->input('to') : $year->ends_on->format('Y-m');

        return view('branch.assets.reports.depreciation', $this->reports->depreciation($from, $to) + ['from' => $from, 'to' => $to, 'year' => $year]);
    }

    public function maintenance(Request $request): View
    {
        $this->authorize('assets.view');
        [$from, $to] = $this->period($request);

        return view('branch.assets.reports.maintenance', $this->reports->maintenance($from, $to) + ['from' => $from, 'to' => $to]);
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    protected function period(Request $request): array
    {
        $from = $request->date('from') ? CarbonImmutable::parse($request->date('from')) : CarbonImmutable::today()->startOfYear();
        $to = $request->date('to') ? CarbonImmutable::parse($request->date('to')) : CarbonImmutable::today();

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
