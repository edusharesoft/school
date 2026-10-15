<?php

namespace App\Services\Reports\Definitions\Assets;

use App\Models\Assets\Asset;
use App\Models\Assets\Category;
use App\Services\Assets\AssetReportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** The fixed-asset register with cost, accumulated depreciation and book value. */
class AssetRegisterReport extends Report
{
    public function __construct(protected AssetReportService $service) {}

    public function key(): string { return 'assets.register'; }

    public function title(): string { return __('Asset register'); }

    public function description(): string { return __('Every asset with its category, place, holder, condition, cost, depreciation and book value.'); }

    public function permission(): string { return 'assets.view'; }

    public function filters(): array
    {
        return [['name' => 'status', 'type' => 'select', 'label' => 'Status', 'default' => 'live', 'options' => ['live' => __('Owned now'), 'in_stock' => __('In store'), 'in_use' => __('In use'), 'repair' => __('Under repair'),
            'missing' => __('Missing'), 'disposed' => __('Disposed'), 'voided' => __('Cancelled')]],
            ['name' => 'category', 'type' => 'select', 'label' => 'Category', 'options' => fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'condition', 'type' => 'select', 'label' => 'Condition', 'options' => ['new' => __('New'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor'), 'damaged' => __('Damaged')]],
            ['name' => 'kind', 'type' => 'select', 'label' => 'Kind', 'options' => ['laboratory' => __('Laboratory equipment')]],
            ['name' => 'q', 'type' => 'text', 'label' => 'Search']];
    }

    public function run(array $filters): R
    {
        $rows = $this->service->registerQuery($filters)->orderBy('code')->get()->map(function (Asset $a) {
            $holder = $a->currentAllocation?->employee?->name ?? $a->currentAllocation?->department?->name ?? $a->currentAllocation?->room?->name ?? $a->currentAllocation?->assignee;

            return ['code' => $a->code, 'name' => $a->displayName(), 'category' => $a->category?->name, 'brand' => trim($a->brand.' '.$a->model), 'serial' => $a->serial_no, 'room' => $a->room?->name ?? $a->location,
                'holder' => $holder, 'condition' => __(ucfirst($a->condition)), 'status' => $a->is_missing ? __('Missing') : ($a->is_under_repair ? __('Under repair') : __(ucfirst(str_replace('_', ' ', $a->status)))),
                'acquired' => $a->acquired_on, 'warranty' => $a->warranty_expires_on, 'cost' => (float) $a->cost, 'accumulated' => (float) $a->accumulated_depreciation, 'book' => $a->bookValue()];
        })->all();

        return $this->result([R::col('code', __('Tag')), R::col('name', __('Asset')), R::col('category', __('Category')), R::col('brand', __('Brand / model')), R::col('serial', __('Serial no')),
            R::col('room', __('Location')), R::col('holder', __('Held by')), R::col('condition', __('Condition')), R::col('status', __('Status')), R::col('acquired', __('Acquired'), 'date'),
            R::col('warranty', __('Warranty until'), 'date'), R::col('cost', __('Cost'), 'money', true), R::col('accumulated', __('Depreciation'), 'money', true), R::col('book', __('Book value'), 'money', true)], $rows);
    }
}
