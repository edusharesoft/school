<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Finance\Vendor;
use App\Models\Inventory\Item;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Requisition;
use App\Services\Inventory\ProcurementService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Purchase orders: draft by a buyer, approved by someone else, received through goods receipts. */
class PurchaseOrderController extends Controller
{
    public function __construct(protected ProcurementService $procurement) {}

    public function index(Request $request): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.orders.index', [
            'orders' => PurchaseOrder::query()->with('vendor:id,name')->withCount('items')
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->integer('vendor_id'), fn ($q, $v) => $q->where('vendor_id', $v))
                ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where('po_no', 'like', "%$t%"))
                ->latest('order_date')->latest('id')->paginate(30)->withQueryString(),
            'vendors' => Vendor::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('inventory.create');
        $from = $request->integer('requisition') ? Requisition::query()->with('items')->where('status', 'approved')->find($request->integer('requisition')) : null;
        $lines = old('lines', $from ? $from->items->map(fn ($i) => ['inventory_item_id' => $i->inventory_item_id, 'quantity' => $i->quantity, 'unit_cost' => $i->estimated_unit_cost])->all()
            : [['inventory_item_id' => '', 'quantity' => '', 'unit_cost' => '']]);

        return view('branch.inventory.orders.create', [
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'items' => Item::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit'])->map(fn ($i) => ['id' => $i->id, 'label' => $i->code.' — '.$i->name, 'unit' => $i->unit])->all(),
            'requisition' => $from, 'lines' => $lines,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.create');
        $data = $request->validate([
            'vendor_id' => ['required', TenantRule::exists('vendors')],
            'inventory_requisition_id' => ['nullable', TenantRule::exists('inventory_requisitions')],
            'order_date' => ['required', 'date'],
            'expected_on' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:80'],
            'lines.*.inventory_item_id' => ['nullable', TenantRule::exists('inventory_items', 'id', true)],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $from = ! empty($data['inventory_requisition_id']) ? Requisition::query()->find($data['inventory_requisition_id']) : null;
        $order = $this->procurement->createOrder($data, $data['lines'], $request->user(), $from);

        return redirect()->route('branch.inventory.orders.show', $order)->with('success', __('Purchase order :no saved as draft.', ['no' => $order->po_no]));
    }

    public function show(PurchaseOrder $order): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.orders.show', ['order' => $order->load(['items.item', 'vendor', 'requisition', 'creator', 'receipts'])]);
    }

    public function print(PurchaseOrder $order): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.orders.print', ['order' => $order->load(['items.item', 'vendor']), 'branch' => current_branch()]);
    }

    public function approve(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $this->procurement->approveOrder($order, $request->user());

        return back()->with('success', __('Purchase order approved — goods can now be received against it.'));
    }

    public function cancel(PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('inventory.update');
        $this->procurement->cancelOrder($order);

        return back()->with('success', __('Purchase order cancelled.'));
    }
}
