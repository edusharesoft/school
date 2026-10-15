<?php

namespace App\Services\Reports\Definitions\Assets;

use App\Services\Assets\AssetReportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Depreciation charged between two months, by category or by month. */
class DepreciationReport extends Report
{
    public function __construct(protected AssetReportService $service) {}

    public function key(): string { return 'assets.depreciation'; }

    public function title(): string { return __('Depreciation'); }

    public function description(): string { return __('Depreciation charged between two months, per category or per month.'); }

    public function permission(): string { return 'assets.view'; }

    public function filters(): array
    {
        return [['name' => 'from_month', 'type' => 'month', 'label' => 'From month', 'default' => fn () => CarbonImmutable::today()->startOfYear()->format('Y-m')],
            ['name' => 'to_month', 'type' => 'month', 'label' => 'To month', 'default' => fn () => CarbonImmutable::today()->format('Y-m')],
            ['name' => 'by', 'type' => 'select', 'label' => 'Group by', 'options' => ['category' => __('Category'), 'month' => __('Month')], 'default' => 'category']];
    }

    public function run(array $filters): R
    {
        $from = $filters['from_month'] ?? CarbonImmutable::today()->startOfYear()->format('Y-m');
        $to = $filters['to_month'] ?? CarbonImmutable::today()->format('Y-m');
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        $data = $this->service->depreciation($from, $to);

        if (($filters['by'] ?? 'category') === 'month') {
            $rows = $data['byMonth']->map(fn ($r) => ['period' => CarbonImmutable::parse($r['period'].'-01')->format('F Y'), 'assets' => $r['assets'], 'amount' => $r['amount']])->all();

            return $this->result([R::col('period', __('Month')), R::col('assets', __('Assets charged'), 'int'), R::col('amount', __('Depreciation'), 'money', true)], $rows);
        }
        $rows = $data['byCategory']->all();

        return $this->result([R::col('category', __('Category')), R::col('assets', __('Assets'), 'int'), R::col('amount', __('Depreciation'), 'money', true)], $rows);
    }
}
