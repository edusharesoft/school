<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockAdjustment;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Support\TenantRule;

/** Stock adjustments: opening stock, damage, loss, expiry, found stock. Needs inventory.approve — it changes the books. */
class AdjustmentController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.adjustments.index', [
            'adjustments' => StockAdjustment::query()->with('creator:id,name')->withCount('items')
                ->when($request->input('reason'), fn ($q, $r) => $q->where('reason', $r))
                ->latest('date')->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('inventory.approve');

        return view('branch.inventory.adjustments.create', [
            'items' => Item::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn ($i) => ['id' => $i->id, 'label' => $i->code.' — '.$i->name, 'unit' => $i->unit, 'stock' => $i->quantity_on_hand, 'cost' => $i->averageCost()])->all(),
            'lines' => old('lines', [['inventory_item_id' => '', 'quantity' => '', 'unit_cost' => '']]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', Rule::in(StockAdjustment::REASONS)],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:80'],
            'lines.*.inventory_item_id' => ['nullable', TenantRule::exists('inventory_items', 'id', true)],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $adjustment = $this->inventory->adjust($data, $data['lines'], $request->user());

        return redirect()->route('branch.inventory.adjustments.show', $adjustment)->with('success', __('Stock adjustment :no posted.', ['no' => $adjustment->adjustment_no]));
    }

    public function show(StockAdjustment $adjustment): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.adjustments.show', ['adjustment' => $adjustment->load(['items.item', 'creator', 'audit'])]);
    }

    public function reverse(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->inventory->reverseAdjustment($adjustment, $data['reason'], $request->user());

        return back()->with('success', __('Adjustment reversed.'));
    }
}
