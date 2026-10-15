<?php

namespace App\Services\Reports\Definitions\Branches;

use App\Services\Dashboard\DashboardService;
use App\Services\Inventory\InventoryReportService;
use App\Services\Reports\ReportResult as R;

/** Inventory of every branch side by side. */
class BranchInventoryReport extends CentralReport
{
    public function __construct(DashboardService $dashboard, protected InventoryReportService $service)
    {
        parent::__construct($dashboard);
    }

    public function key(): string { return 'branches.inventory'; }

    public function title(): string { return __('Branch-wise inventory'); }

    public function description(): string { return __('Stock value, shortages, open orders and supplier dues of every branch.'); }

    public function permission(): string { return 'inventory.view'; }

    public function run(array $filters): R
    {
        $rows = $this->service->consolidated($this->branchIds())['rows']->map(fn ($r) => ['branch' => $r['branch']->name, 'code' => $r['branch']->code, 'items' => $r['items'], 'value' => $r['value'], 'low' => $r['low'],
            'orders' => $r['openOrders'], 'order_value' => $r['openOrderValue'], 'purchased' => $r['purchased'], 'due' => $r['due']])->all();

        return $this->result([R::col('branch', __('Branch')), R::col('code', __('Code')), R::col('items', __('Items'), 'int', true), R::col('value', __('Stock value'), 'money', true),
            R::col('low', __('Low stock'), 'int', true), R::col('orders', __('Open orders'), 'int', true), R::col('order_value', __('Open order value'), 'money', true),
            R::col('purchased', __('Bought this year'), 'money', true), R::col('due', __('Owed to suppliers'), 'money', true)], $rows);
    }
}
