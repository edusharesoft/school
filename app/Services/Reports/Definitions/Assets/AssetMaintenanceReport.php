<?php

namespace App\Services\Reports\Definitions\Assets;

use App\Models\Assets\MaintenanceLog;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Completed maintenance, repairs and calibrations of a period with their cost. */
class AssetMaintenanceReport extends Report
{
    public function key(): string { return 'assets.maintenance'; }

    public function title(): string { return __('Asset maintenance'); }

    public function description(): string { return __('Repairs, services and calibrations completed in a period with their cost.'); }

    public function permission(): string { return 'assets.view'; }

    public function filters(): array
    {
        return [...$this->periodFilters(), ['name' => 'kind', 'type' => 'select', 'label' => 'Kind', 'options' => ['repair' => __('Repair'), 'preventive' => __('Preventive'), 'calibration' => __('Calibration'), 'inspection' => __('Inspection'), 'safety' => __('Safety check'), 'upgrade' => __('Upgrade')]]];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $rows = MaintenanceLog::query()->with(['asset:id,code,name,name_bn', 'vendor:id,name', 'employee:id,name'])->where('status', 'completed')
            ->whereDate('completed_on', '>=', $from->toDateString())->whereDate('completed_on', '<=', $to->toDateString())
            ->when($filters['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))->orderBy('completed_on')->orderBy('id')->get()
            ->map(fn (MaintenanceLog $m) => ['date' => $m->completed_on, 'log' => $m->log_no, 'asset' => $m->asset ? $m->asset->code.' — '.$m->asset->displayName() : null, 'kind' => __(ucfirst($m->kind)), 'title' => $m->title,
                'vendor' => $m->vendor?->name, 'by' => $m->employee?->name, 'reported' => $m->reported_on, 'days' => $m->reported_on && $m->completed_on ? (int) $m->reported_on->diffInDays($m->completed_on) : null,
                'outcome' => $m->outcome, 'cost' => (float) $m->cost])->all();

        return $this->result([R::col('date', __('Completed'), 'date'), R::col('log', __('Job no')), R::col('asset', __('Asset')), R::col('kind', __('Kind')), R::col('title', __('Work')), R::col('vendor', __('Vendor')),
            R::col('by', __('Responsible')), R::col('reported', __('Reported'), 'date'), R::col('days', __('Days taken'), 'int'), R::col('outcome', __('Outcome')), R::col('cost', __('Cost'), 'money', true)], $rows);
    }
}
