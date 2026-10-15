<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Services\Inventory\InventoryReportService;
use App\Services\Inventory\InventoryService;
use App\Support\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Item catalogue with stock on hand; item page = stock card. */
class ItemController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(Request $request, InventoryReportService $reports): View
    {
        $this->authorize('inventory.view');
        $filters = $request->only(['q', 'category', 'status']);

        return view('branch.inventory.items.index', [
            'items' => $reports->stock($filters),
            'totals' => $reports->stockTotals($filters),
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('inventory.create');

        return view('branch.inventory.items.form', ['item' => null] + $this->lookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.create');
        $data = $this->validated($request);
        $opening = $request->validate([
            'opening_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'opening_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $item = $this->inventory->createItem($data);

        if ((float) ($opening['opening_quantity'] ?? 0) > 0) {
            if (! $request->user()->can('inventory.approve')) {
                return redirect()->route('branch.inventory.items.show', $item)->with('warning', __('Item saved, but opening stock needs the approve permission — ask the store manager to post it as a stock adjustment.'));
            }
            $this->inventory->adjust(['date' => now()->toDateString(), 'reason' => 'opening', 'note' => __('Opening stock of :item', ['item' => $item->name])],
                [['inventory_item_id' => $item->id, 'quantity' => $opening['opening_quantity'], 'unit_cost' => $opening['opening_unit_cost'] ?? 0]], $request->user());
        }

        return redirect()->route('branch.inventory.items.show', $item)->with('success', __('Item saved.'));
    }

    public function show(Request $request, Item $item, InventoryReportService $reports): View
    {
        $this->authorize('inventory.view');
        $from = $request->date('from') ? CarbonImmutable::parse($request->date('from')) : CarbonImmutable::today()->subMonths(3);
        $to = $request->date('to') ? CarbonImmutable::parse($request->date('to')) : CarbonImmutable::today();

        return view('branch.inventory.items.show', ['item' => $item->load('category'), 'card' => $reports->stockCard($item, $from, $to), 'from' => $from, 'to' => $to]);
    }

    public function edit(Item $item): View
    {
        $this->authorize('inventory.update');

        return view('branch.inventory.items.form', ['item' => $item] + $this->lookups());
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('inventory.update');
        $item->update($this->validated($request, $item));

        return redirect()->route('branch.inventory.items.show', $item)->with('success', __('Item saved.'));
    }

    public function destroy(Item $item): RedirectResponse
    {
        $this->authorize('inventory.delete');
        if ($item->transactions()->exists() || $item->quantity_on_hand > 0) {
            return back()->with('warning', __('This item has stock movements — mark it inactive instead.'));
        }
        $item->delete();

        return redirect()->route('branch.inventory.items.index')->with('success', __('Item deleted.'));
    }

    protected function lookups(): array
    {
        return ['categories' => Category::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')];
    }

    protected function validated(Request $request, ?Item $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('inventory_items', 'name', $item?->id)->whereNull('deleted_at')],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'inventory_category_id' => ['required', TenantRule::exists('inventory_categories')],
            'item_type' => ['required', Rule::in(Item::TYPES)],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'location' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $data['reorder_level'] = (float) ($data['reorder_level'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
