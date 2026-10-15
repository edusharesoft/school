<?php

namespace App\Services\Reports\Definitions\Assets;

use App\Services\Assets\AssetReportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Cost, depreciation and book value per category. */
class AssetSummaryReport extends Report
{
    public function __construct(protected AssetReportService $service) {}

    public function key(): string { return 'assets.summary'; }

    public function title(): string { return __('Asset summary by category'); }

    public function description(): string { return __('Number of assets, cost, accumulated depreciation and book value per category.'); }

    public function permission(): string { return 'assets.view'; }

    public function run(array $filters): R
    {
        $rows = $this->service->byCategory()->map(fn ($r) => ['category' => $r['category'], 'kind' => $r['kind'] === 'laboratory' ? __('Laboratory') : __('General'), 'count' => $r['count'],
            'cost' => $r['cost'], 'accumulated' => $r['accumulated'], 'book' => $r['book']])->all();

        return $this->result([R::col('category', __('Category')), R::col('kind', __('Kind')), R::col('count', __('Assets'), 'int', true), R::col('cost', __('Cost'), 'money', true),
            R::col('accumulated', __('Depreciation'), 'money', true), R::col('book', __('Book value'), 'money', true)], $rows);
    }
}
