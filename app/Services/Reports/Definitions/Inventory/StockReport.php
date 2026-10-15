<?php

namespace App\Services\Reports\Definitions\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Stock on hand with value at average cost; low and out-of-stock items flagged. */
class StockReport extends Report
{
    use Lookups;

    public function key(): string { return 'inventory.stock'; }

    public function title(): string { return __('Stock and valuation'); }

    public function description(): string { return __('Quantity and value of every item at average cost, with shortages flagged.'); }

    public function permission(): string { return 'inventory.view'; }

    public function filters(): array
    {
        return [['name' => 'category', 'type' => 'select', 'label' => 'Category', 'options' => fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'status', 'type' => 'select', 'label' => 'Stock level', 'options' => ['low' => __('Low stock'), 'out' => __('Out of stock')]],
            ['name' => 'q', 'type' => 'text', 'label' => 'Search']];
    }

    public function run(array $filters): R
    {
        $items = Item::query()->with('category:id,name')->where('is_active', true)
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('name_bn', 'like', "%$t%")->orWhere('code', 'like', "%$t%")))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('inventory_category_id', $v))
            ->when(($filters['status'] ?? null) === 'low', fn ($q) => $q->low())
            ->when(($filters['status'] ?? null) === 'out', fn ($q) => $q->where('quantity_on_hand', '<=', 0))
            ->orderBy('name')->get();

        $rows = $items->map(function (Item $i) {
            $qty = (float) $i->quantity_on_hand;
            $state = $qty <= 0 ? __('Out of stock') : (($i->reorder_level > 0 && $qty <= (float) $i->reorder_level) ? __('Low stock') : __('OK'));

            return ['code' => $i->code, 'name' => $this->bn($i->name, $i->name_bn), 'category' => $i->category?->name, 'type' => __(ucfirst($i->item_type)), 'unit' => $i->unit, 'qty' => $qty,
                'reorder' => (float) $i->reorder_level, 'avg' => $qty > 0 ? round((float) $i->stock_value / $qty, 2) : 0, 'value' => (float) $i->stock_value, 'state' => $state, 'location' => $i->location];
        })->all();

        return $this->result([R::col('code', __('Code')), R::col('name', __('Item')), R::col('category', __('Category')), R::col('type', __('Type')), R::col('unit', __('Unit')), R::col('qty', __('On hand'), 'number'),
            R::col('reorder', __('Reorder level'), 'number'), R::col('avg', __('Average cost'), 'money'), R::col('value', __('Value'), 'money', true), R::col('state', __('Level')), R::col('location', __('Location'))], $rows);
    }
}
