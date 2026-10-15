<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\Inventory\GoodsReceipt;
use App\Models\Inventory\Item;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Requisition;
use App\Models\Inventory\StockIssue;
use App\Models\Inventory\StockIssueItem;
use App\Models\Inventory\StockTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventory figures for the home screen, the reports and the institution-wide (central) view.
 * Branch methods run inside a branch context (BranchScope); consolidated() takes branch ids explicitly.
 */
class InventoryReportService
{
    public function __construct(protected ProcurementService $procurement) {}

    /** Figures for the inventory home screen. */
    public function overview(): array
    {
        $stock = Item::query()->where('is_active', true)->selectRaw('COUNT(*) as items, COALESCE(SUM(stock_value),0) as value')->toBase()->first();

        return [
            'items' => (int) $stock->items,
            'value' => round((float) $stock->value, 2),
            'low' => Item::query()->low()->count(),
            'pendingRequests' => Requisition::query()->where('status', 'pending')->count(),
            'openOrders' => PurchaseOrder::query()->whereIn('status', ['approved', 'partial'])->count(),
            'draftOrders' => PurchaseOrder::query()->where('status', 'draft')->count(),
            'payable' => round((float) $this->procurement->payables()->sum('due'), 2),
            'lowItems' => Item::query()->with('category')->low()->orderByRaw('quantity_on_hand / reorder_level')->limit(8)->get(),
            'recent' => StockTransaction::query()->with('item')->latest('id')->limit(8)->get(),
        ];
    }

    /** Stock list with value; $filters: q, category, status (low|out|all). */
    public function stock(array $filters, int $perPage = 40): LengthAwarePaginator
    {
        return $this->stockQuery($filters)->with('category')->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function stockTotals(array $filters): array
    {
        $row = $this->stockQuery($filters)->toBase()->selectRaw('COUNT(*) as items, COALESCE(SUM(stock_value),0) as value')->first();

        return ['items' => (int) $row->items, 'value' => round((float) $row->value, 2)];
    }

    protected function stockQuery(array $filters)
    {
        return Item::query()->where('is_active', true)
            ->when(trim((string) ($filters['q'] ?? '')), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('name_bn', 'like', "%$t%")->orWhere('code', 'like', "%$t%")))
            ->when(! empty($filters['category']), fn ($q) => $q->where('inventory_category_id', (int) $filters['category']))
            ->when(($filters['status'] ?? '') === 'low', fn ($q) => $q->low())
            ->when(($filters['status'] ?? '') === 'out', fn ($q) => $q->where('quantity_on_hand', '<=', 0));
    }

    /** Value of stock per category. */
    public function valuationByCategory(): Collection
    {
        return Item::query()->join('inventory_categories', 'inventory_categories.id', '=', 'inventory_items.inventory_category_id')
            ->whereNull('inventory_items.deleted_at')->where('inventory_items.is_active', true)
            ->selectRaw('inventory_categories.name as category, COUNT(*) as items, COALESCE(SUM(inventory_items.stock_value),0) as value')
            ->groupBy('inventory_categories.id', 'inventory_categories.name')->orderBy('inventory_categories.name')->toBase()->get()
            ->map(fn ($r) => ['category' => $r->category, 'items' => (int) $r->items, 'value' => round((float) $r->value, 2)]);
    }

    /** Stock card of one item for a period: balance brought forward, lines, balance carried. */
    public function stockCard(Item $item, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $before = StockTransaction::query()->where('inventory_item_id', $item->id)->whereDate('date', '<', $from->toDateString())->orderByDesc('date')->orderByDesc('id')->first();
        $lines = StockTransaction::query()->with('creator')->where('inventory_item_id', $item->id)
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->orderBy('date')->orderBy('id')->get();

        return [
            'openingQuantity' => (float) ($before->balance_quantity ?? 0), 'openingValue' => (float) ($before->balance_value ?? 0),
            'lines' => $lines,
            'in' => round($lines->where('quantity', '>', 0)->sum('quantity'), 3), 'out' => round(abs($lines->where('quantity', '<', 0)->sum('quantity')), 3),
            'closingQuantity' => (float) ($lines->last()->balance_quantity ?? $before->balance_quantity ?? 0), 'closingValue' => (float) ($lines->last()->balance_value ?? $before->balance_value ?? 0),
        ];
    }

    /** What was issued in a period, by item and by department. */
    public function consumption(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $issues = StockIssue::query()->where('status', 'issued')->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString());
        $ids = (clone $issues)->pluck('id');

        $byItem = StockIssueItem::query()->join('inventory_items', 'inventory_items.id', '=', 'stock_issue_items.inventory_item_id')
            ->whereIn('stock_issue_items.stock_issue_id', $ids)
            ->selectRaw('inventory_items.id, inventory_items.name, inventory_items.unit, SUM(stock_issue_items.quantity) as quantity, SUM(stock_issue_items.amount) as value')
            ->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.unit')->orderByDesc('value')->toBase()->get();

        $byDepartment = (clone $issues)->leftJoin('departments', 'departments.id', '=', 'stock_issues.department_id')
            ->selectRaw('COALESCE(departments.name, ?) as department, COUNT(*) as issues, SUM(stock_issues.total) as value', [__('Other')])
            ->groupBy('departments.name')->orderByDesc('value')->toBase()->get();

        return ['byItem' => $byItem, 'byDepartment' => $byDepartment, 'total' => round((float) $byItem->sum('value'), 2), 'issues' => $ids->count()];
    }

    /** Goods received in a period with a total per supplier. */
    public function purchases(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $receipts = GoodsReceipt::query()->with('vendor')->where('status', 'posted')
            ->whereDate('received_on', '>=', $from->toDateString())->whereDate('received_on', '<=', $to->toDateString())->orderBy('received_on')->orderBy('id')->get();
        $bySupplier = $receipts->groupBy('vendor_id')->map(fn ($g) => ['vendor' => $g->first()->vendor?->name, 'receipts' => $g->count(), 'total' => round($g->sum('total'), 2), 'due' => round($g->sum(fn ($r) => $r->due()), 2)])
            ->sortByDesc('total')->values();

        return ['receipts' => $receipts, 'bySupplier' => $bySupplier, 'total' => round($receipts->sum('total'), 2)];
    }

    /** Institution-wide: each branch's stock, shortages, open orders and supplier dues side by side. @param int[] $branchIds */
    public function consolidated(array $branchIds): array
    {
        $branches = Branch::query()->whereIn('id', $branchIds)->ordered()->get(['id', 'name', 'code']);
        $stock = DB::table('inventory_items')->whereIn('branch_id', $branchIds)->whereNull('deleted_at')->where('is_active', true)
            ->selectRaw('branch_id, COUNT(*) as items, SUM(stock_value) as value, SUM(CASE WHEN reorder_level > 0 AND quantity_on_hand <= reorder_level THEN 1 ELSE 0 END) as low')
            ->groupBy('branch_id')->get()->keyBy('branch_id');
        $orders = DB::table('purchase_orders')->whereIn('branch_id', $branchIds)->whereIn('status', ['approved', 'partial'])->selectRaw('branch_id, COUNT(*) as n, SUM(total) as value')->groupBy('branch_id')->get()->keyBy('branch_id');
        $dues = DB::table('goods_receipts')->whereIn('branch_id', $branchIds)->where('status', 'posted')->where('payment_mode', 'credit')
            ->selectRaw('branch_id, SUM(total - paid_amount) as due')->groupBy('branch_id')->pluck('due', 'branch_id');
        $purchased = DB::table('goods_receipts')->whereIn('branch_id', $branchIds)->where('status', 'posted')
            ->whereDate('received_on', '>=', CarbonImmutable::today()->startOfYear()->toDateString())->selectRaw('branch_id, SUM(total) as total')->groupBy('branch_id')->pluck('total', 'branch_id');

        $rows = $branches->map(fn (Branch $b) => [
            'branch' => $b, 'items' => (int) ($stock[$b->id]->items ?? 0), 'value' => round((float) ($stock[$b->id]->value ?? 0), 2), 'low' => (int) ($stock[$b->id]->low ?? 0),
            'openOrders' => (int) ($orders[$b->id]->n ?? 0), 'openOrderValue' => round((float) ($orders[$b->id]->value ?? 0), 2),
            'due' => round((float) ($dues[$b->id] ?? 0), 2), 'purchased' => round((float) ($purchased[$b->id] ?? 0), 2),
        ]);

        return ['rows' => $rows, 'total' => [
            'items' => $rows->sum('items'), 'value' => round($rows->sum('value'), 2), 'low' => $rows->sum('low'), 'openOrders' => $rows->sum('openOrders'),
            'openOrderValue' => round($rows->sum('openOrderValue'), 2), 'due' => round($rows->sum('due'), 2), 'purchased' => round($rows->sum('purchased'), 2),
        ]];
    }
}
