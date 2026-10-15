<?php

namespace App\Services\Reports\Definitions\Branches;

use App\Services\Dashboard\DashboardService;
use App\Services\Finance\ConsolidatedReportService;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Income, expense, collection and dues of every branch side by side. */
class BranchFinanceReport extends CentralReport
{
    public function __construct(DashboardService $dashboard, protected ConsolidatedReportService $service)
    {
        parent::__construct($dashboard);
    }

    public function key(): string { return 'branches.finance'; }

    public function title(): string { return __('Branch-wise finance'); }

    public function description(): string { return __('Income, expenditure, surplus, fees collected, outstanding dues and cash of every branch.'); }

    public function permission(): string { return 'financial-reports.view'; }

    public function exportPermission(): string { return 'financial-reports.export'; }

    public function filters(): array
    {
        return [['name' => 'from', 'type' => 'date', 'label' => 'From', 'default' => fn () => CarbonImmutable::today()->startOfYear()->toDateString()],
            ['name' => 'to', 'type' => 'date', 'label' => 'To', 'default' => $this->toDefault()]];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $rows = $this->service->summary($this->branchIds(), $from, $to)['rows']->map(fn ($r) => ['branch' => $r['branch']->name, 'code' => $r['branch']->code, 'income' => $r['income'], 'expense' => $r['expense'],
            'surplus' => $r['surplus'], 'collected' => $r['collected'], 'outstanding' => $r['outstanding'], 'cash' => $r['cash']])->all();

        return $this->result([R::col('branch', __('Branch')), R::col('code', __('Code')), R::col('income', __('Income'), 'money', true), R::col('expense', __('Expenditure'), 'money', true),
            R::col('surplus', __('Surplus / (deficit)'), 'money', true), R::col('collected', __('Fees collected'), 'money', true), R::col('outstanding', __('Fees outstanding'), 'money', true), R::col('cash', __('Cash and bank'), 'money', true)], $rows);
    }
}
