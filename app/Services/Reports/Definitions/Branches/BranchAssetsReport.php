<?php

namespace App\Services\Reports\Definitions\Branches;

use App\Services\Assets\AssetReportService;
use App\Services\Dashboard\DashboardService;
use App\Services\Reports\ReportResult as R;

/** Assets of every branch side by side. */
class BranchAssetsReport extends CentralReport
{
    public function __construct(DashboardService $dashboard, protected AssetReportService $service)
    {
        parent::__construct($dashboard);
    }

    public function key(): string { return 'branches.assets'; }

    public function title(): string { return __('Branch-wise assets'); }

    public function description(): string { return __('Assets, cost, book value, repairs, overdue maintenance and disposals of every branch.'); }

    public function permission(): string { return 'assets.view'; }

    public function run(array $filters): R
    {
        $rows = $this->service->consolidated($this->branchIds())['rows']->map(fn ($r) => ['branch' => $r['branch']->name, 'code' => $r['branch']->code, 'count' => $r['count'], 'cost' => $r['cost'],
            'accumulated' => $r['accumulated'], 'book' => $r['book'], 'repair' => $r['repair'], 'missing' => $r['missing'], 'overdue' => $r['overdue'], 'disposed' => $r['disposed'], 'gain' => $r['gainLoss']])->all();

        return $this->result([R::col('branch', __('Branch')), R::col('code', __('Code')), R::col('count', __('Assets'), 'int', true), R::col('cost', __('Cost'), 'money', true),
            R::col('accumulated', __('Depreciation'), 'money', true), R::col('book', __('Book value'), 'money', true), R::col('repair', __('Under repair'), 'int', true), R::col('missing', __('Missing'), 'int', true),
            R::col('overdue', __('Overdue maintenance'), 'int', true), R::col('disposed', __('Disposed this year'), 'int', true), R::col('gain', __('Disposal gain / (loss)'), 'money', true)], $rows);
    }
}
