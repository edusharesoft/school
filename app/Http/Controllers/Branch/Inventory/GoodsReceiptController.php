<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\Vendor;
use App\Models\Inventory\GoodsReceipt;
use App\Models\Inventory\Item;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\SupplierPayment;
use App\Services\Inventory\ProcurementService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Goods receiving (with or without a purchase order) and payments to suppliers. */
class GoodsReceiptController extends Controller
{
    public function __construct(protected ProcurementService $procurement) {}

    public function index(Request $request): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.receipts.index', [
            'receipts' => GoodsReceipt::query()->with(['vendor:id,name', 'order:id,po_no'])
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->boolean('due'), fn ($q) => $q->where('status', 'posted')->where('payment_mode', 'credit')->whereColumn('paid_amount', '<', 'total'))
                ->when($request->integer('vendor_id'), fn ($q, $v) => $q->where('vendor_id', $v))
                ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('grn_no', 'like', "%$t%")->orWhere('bill_no', 'like', "%$t%")))
                ->latest('received_on')->latest('id')->paginate(30)->withQueryString(),
            'vendors' => Vendor::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('inventory.create');
        $order = $request->integer('order') ? PurchaseOrder::query()->with(['items.item', 'vendor'])->whereIn('status', ['approved', 'partial'])->find($request->integer('order')) : null;
        $lines = old('lines', $order
            ? $order->items->filter(fn ($i) => $i->pending() > 0)->map(fn ($i) => ['inventory_item_id' => $i->inventory_item_id, 'purchase_order_item_id' => $i->id, 'quantity' => $i->pending(), 'unit_cost' => $i->unit_cost])->values()->all()
            : [['inventory_item_id' => '', 'quantity' => '', 'unit_cost' => '']]);

        return view('branch.inventory.receipts.create', [
            'order' => $order, 'lines' => $lines,
            'openOrders' => PurchaseOrder::query()->with('vendor:id,name')->whereIn('status', ['approved', 'partial'])->orderBy('po_no')->get(),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'items' => Item::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit'])->map(fn ($i) => ['id' => $i->id, 'label' => $i->code.' — '.$i->name, 'unit' => $i->unit])->all(),
            'moneyAccounts' => Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.create');
        $data = $request->validate([
            'purchase_order_id' => ['nullable', TenantRule::exists('purchase_orders')],
            'vendor_id' => ['required_without:purchase_order_id', 'nullable', TenantRule::exists('vendors')],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'bill_no' => ['nullable', 'string', 'max:60'],
            'payment_mode' => ['required', Rule::in(['credit', 'paid'])],
            'paid_from_account_id' => ['required_if:payment_mode,paid', 'nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:80'],
            'lines.*.inventory_item_id' => ['nullable', TenantRule::exists('inventory_items', 'id', true)],
            'lines.*.purchase_order_item_id' => ['nullable', TenantRule::exists('purchase_order_items')],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $receipt = $this->procurement->receive($data, $data['lines'], $request->user());

        return redirect()->route('branch.inventory.receipts.show', $receipt)->with('success', __('Goods receipt :no posted.', ['no' => $receipt->grn_no]));
    }

    public function show(GoodsReceipt $receipt): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.receipts.show', [
            'receipt' => $receipt->load(['items.item', 'vendor', 'order', 'payments.account', 'paidFrom', 'receiver']),
            'moneyAccounts' => Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
        ]);
    }

    public function reverse(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->procurement->reverseReceipt($receipt, $data['reason'], $request->user());

        return back()->with('success', __('Receipt reversed.'));
    }

    public function pay(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'], 'date' => ['required', 'date', 'before_or_equal:today'],
            'account_id' => ['required', 'integer'], 'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $from = Account::query()->money()->findOrFail($data['account_id']);
        $payment = $this->procurement->paySupplier($receipt, (float) $data['amount'], $from, $data['date'], $data['reference'] ?? null, $request->user());

        return back()->with('success', __('Payment :no recorded.', ['no' => $payment->payment_no]));
    }

    public function reversePayment(Request $request, SupplierPayment $payment): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->procurement->reversePayment($payment, $data['reason'], $request->user());

        return back()->with('success', __('Payment reversed.'));
    }
}
