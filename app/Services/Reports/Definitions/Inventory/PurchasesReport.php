<?php

namespace App\Services\Reports\Definitions\Inventory;

use App\Models\Inventory\GoodsReceipt;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Goods received in a period with what was paid and what is still owed to the supplier. */
class PurchasesReport extends Report
{
    public function key(): string { return 'inventory.purchases'; }

    public function title(): string { return __('Purchases and supplier dues'); }

    public function description(): string { return __('Goods received in a period: supplier, bill, total, paid and still owed.'); }

    public function permission(): string { return 'inventory.view'; }

    public function filters(): array
    {
        return [...$this->periodFilters(), ['name' => 'owed', 'type' => 'bool', 'label' => 'Only receipts with a balance due']];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $rows = GoodsReceipt::query()->with('vendor:id,name')->where('status', 'posted')
            ->whereDate('received_on', '>=', $from->toDateString())->whereDate('received_on', '<=', $to->toDateString())->orderBy('received_on')->orderBy('id')->get()
            ->map(fn (GoodsReceipt $g) => ['date' => $g->received_on, 'grn' => $g->grn_no, 'supplier' => $g->vendor?->name, 'bill' => $g->bill_no, 'mode' => $g->payment_mode === 'paid' ? __('Paid now') : __('On credit'),
                'total' => (float) $g->total, 'paid' => (float) $g->paid_amount, 'due' => $g->due()])
            ->when(! empty($filters['owed']), fn ($c) => $c->filter(fn ($r) => $r['due'] > 0.004))->values()->all();

        return $this->result([R::col('date', __('Date'), 'date'), R::col('grn', __('Receipt')), R::col('supplier', __('Supplier')), R::col('bill', __('Bill no')), R::col('mode', __('Payment')),
            R::col('total', __('Total'), 'money', true), R::col('paid', __('Paid'), 'money', true), R::col('due', __('Due'), 'money', true)], $rows);
    }
}
