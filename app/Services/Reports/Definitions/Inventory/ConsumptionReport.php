<?php

namespace App\Services\Reports\Definitions\Inventory;

use App\Services\Inventory\InventoryReportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** What was issued from the store in a period, by item or by department. */
class ConsumptionReport extends Report
{
    public function __construct(protected InventoryReportService $service) {}

    public function key(): string { return 'inventory.consumption'; }

    public function title(): string { return __('Stock consumption'); }

    public function description(): string { return __('Goods issued from the store in a period, by item or by department, at average cost.'); }

    public function permission(): string { return 'inventory.view'; }

    public function filters(): array
    {
        return [...$this->periodFilters(), ['name' => 'by', 'type' => 'select', 'label' => 'Group by', 'options' => ['item' => __('Item'), 'department' => __('Department')], 'default' => 'item']];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $data = $this->service->consumption($from, $to);

        if (($filters['by'] ?? 'item') === 'department') {
            $rows = $data['byDepartment']->map(fn ($r) => ['department' => $r->department, 'issues' => (int) $r->issues, 'value' => round((float) $r->value, 2)])->all();

            return $this->result([R::col('department', __('Department')), R::col('issues', __('Issue slips'), 'int', true), R::col('value', __('Value'), 'money', true)], $rows);
        }

        $rows = $data['byItem']->map(fn ($r) => ['name' => $r->name, 'unit' => $r->unit, 'quantity' => (float) $r->quantity, 'value' => round((float) $r->value, 2)])->all();

        return $this->result([R::col('name', __('Item')), R::col('unit', __('Unit')), R::col('quantity', __('Quantity'), 'number', true), R::col('value', __('Value'), 'money', true)], $rows);
    }
}
