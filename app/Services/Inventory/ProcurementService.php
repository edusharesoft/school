<?php

namespace App\Services\Inventory;

use App\Models\Finance\Account;
use App\Models\Finance\Vendor;
use App\Models\Inventory\GoodsReceipt;
use App\Models\Inventory\GoodsReceiptItem;
use App\Models\Inventory\Item;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\Requisition;
use App\Models\Inventory\RequisitionItem;
use App\Models\Inventory\SupplierPayment;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buying:   purchase request ──approve──▶ purchase order (draft ──approve──▶ approved ──receive──▶ partial / received)
 *           goods receipt   Dr Inventory stock / Cr Accounts payable   (or Cr cash / bank when paid at once)
 *           supplier payment Dr Accounts payable / Cr cash / bank, against one receipt
 * Nobody approves their own request or order. A receipt can be reversed while the stock it brought in is still on the shelves
 * and no supplier payment is attached to it (reverse the payment first).
 */
class ProcurementService
{
    public function __construct(
        protected InventoryService $inventory,
        protected LedgerService $ledger,
        protected ChartOfAccounts $chart,
    ) {}

    // ───────────────────────────── purchase requests ─────────────────────────────

    /** @param array<int,array{inventory_item_id:mixed, quantity:mixed, estimated_unit_cost?:mixed, note?:?string}> $lines */
    public function createRequisition(array $data, array $lines, User $user): Requisition
    {
        $lines = $this->cleanLines($lines, 'estimated_unit_cost');
        $date = CarbonImmutable::parse($data['date']);

        return DB::transaction(function () use ($data, $lines, $user, $date) {
            $this->assertItems($lines->pluck('inventory_item_id')->all());
            $req = new Requisition(['date' => $date->toDateString(), 'needed_by' => $data['needed_by'] ?? null, 'department_id' => $data['department_id'] ?? null, 'purpose' => $data['purpose']]);
            $req->forceFill(['requisition_no' => $this->inventory->docNo('PR', $date), 'status' => 'pending', 'requested_by' => $user->id])->save();
            foreach ($lines as $l) {
                RequisitionItem::query()->create(['inventory_requisition_id' => $req->id, 'inventory_item_id' => $l['inventory_item_id'], 'quantity' => $l['quantity'],
                    'estimated_unit_cost' => $l['estimated_unit_cost'], 'note' => $this->clip($l['note'] ?? null)]);
            }

            return $req;
        });
    }

    public function approveRequisition(Requisition $req, User $user, ?string $note = null): Requisition
    {
        $this->assertStatus($req, 'pending', __('This request has already been reviewed.'));
        if ((int) $req->requested_by === (int) $user->id) {
            throw ValidationException::withMessages(['status' => __('You cannot approve your own purchase request.')]);
        }
        $req->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'review_note' => $this->clip($note)])->save();

        return $req;
    }

    public function rejectRequisition(Requisition $req, string $note, User $user): Requisition
    {
        $this->assertStatus($req, 'pending', __('This request has already been reviewed.'));
        $req->forceFill(['status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'review_note' => $this->clip($note)])->save();

        return $req;
    }

    public function cancelRequisition(Requisition $req): Requisition
    {
        if (! in_array($req->status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages(['status' => __('Only a pending or approved request can be cancelled.')]);
        }
        $req->forceFill(['status' => 'cancelled'])->save();

        return $req;
    }

    // ───────────────────────────── purchase orders ─────────────────────────────

    /** @param array<int,array{inventory_item_id:mixed, quantity:mixed, unit_cost:mixed}> $lines */
    public function createOrder(array $data, array $lines, User $user, ?Requisition $from = null): PurchaseOrder
    {
        $lines = $this->cleanLines($lines, 'unit_cost');
        $date = CarbonImmutable::parse($data['order_date']);
        if ($from && $from->status !== 'approved') {
            throw ValidationException::withMessages(['requisition' => __('Only an approved purchase request can be turned into an order.')]);
        }

        return DB::transaction(function () use ($data, $lines, $user, $date, $from) {
            $this->assertItems($lines->pluck('inventory_item_id')->all());
            Vendor::query()->where('is_active', true)->findOr($data['vendor_id'], fn () => throw ValidationException::withMessages(['vendor_id' => __('Choose an active supplier.')]));

            $order = new PurchaseOrder(['vendor_id' => $data['vendor_id'], 'order_date' => $date->toDateString(), 'expected_on' => $data['expected_on'] ?? null, 'notes' => $this->clip($data['notes'] ?? null)]);
            $order->forceFill(['po_no' => $this->inventory->docNo('PO', $date), 'status' => 'draft', 'created_by' => $user->id, 'inventory_requisition_id' => $from?->id, 'total' => 0])->save();
            $total = 0.0;
            foreach ($lines as $l) {
                PurchaseOrderItem::query()->create(['purchase_order_id' => $order->id, 'inventory_item_id' => $l['inventory_item_id'], 'quantity' => $l['quantity'], 'unit_cost' => $l['unit_cost']]);
                $total += round($l['quantity'] * $l['unit_cost'], 2);
            }
            $order->forceFill(['total' => round($total, 2)])->save();
            $from?->forceFill(['status' => 'ordered'])->save();

            return $order;
        });
    }

    public function approveOrder(PurchaseOrder $order, User $user): PurchaseOrder
    {
        $this->assertStatus($order, 'draft', __('Only a draft order can be approved.'));
        if ((int) $order->created_by === (int) $user->id) {
            throw ValidationException::withMessages(['status' => __('You cannot approve your own purchase order.')]);
        }
        $order->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();

        return $order;
    }

    public function cancelOrder(PurchaseOrder $order): PurchaseOrder
    {
        if (! in_array($order->status, ['draft', 'approved'], true) || $order->receipts()->where('status', 'posted')->exists()) {
            throw ValidationException::withMessages(['status' => __('Only an order with nothing received can be cancelled.')]);
        }
        DB::transaction(function () use ($order) {
            $order->forceFill(['status' => 'cancelled'])->save();
            // the request it came from can be ordered again
            if ($order->requisition && $order->requisition->status === 'ordered') {
                $order->requisition->forceFill(['status' => 'approved'])->save();
            }
        });

        return $order;
    }

    // ───────────────────────────── goods receiving ─────────────────────────────

    /**
     * @param  array{vendor_id?:mixed, purchase_order_id?:mixed, received_on:string, bill_no?:?string, payment_mode:string, paid_from_account_id?:mixed, note?:?string}  $data
     * @param  array<int,array{inventory_item_id:mixed, quantity:mixed, unit_cost:mixed, purchase_order_item_id?:mixed}>  $lines
     */
    public function receive(array $data, array $lines, User $user): GoodsReceipt
    {
        $lines = $this->cleanLines($lines, 'unit_cost');
        $date = CarbonImmutable::parse($data['received_on']);
        $paid = ($data['payment_mode'] ?? 'credit') === 'paid';
        if ($paid && ! Account::query()->money()->whereKey((int) ($data['paid_from_account_id'] ?? 0))->exists()) {
            throw ValidationException::withMessages(['paid_from_account_id' => __('Choose the cash, bank or mobile-banking account the money was paid from.')]);
        }

        return DB::transaction(function () use ($data, $lines, $user, $date, $paid) {
            $order = null;
            if (! empty($data['purchase_order_id'])) {
                $order = PurchaseOrder::query()->whereKey($data['purchase_order_id'])->lockForUpdate()->firstOrFail();
                if (! $order->isReceivable()) {
                    throw ValidationException::withMessages(['purchase_order_id' => __('This order is not open for receiving.')]);
                }
                $data['vendor_id'] = $order->vendor_id;
            }
            if (empty($data['vendor_id']) || ! Vendor::query()->whereKey($data['vendor_id'])->exists()) {
                throw ValidationException::withMessages(['vendor_id' => __('Choose a supplier.')]);
            }
            $this->assertItems($lines->pluck('inventory_item_id')->all());

            $poItems = $order ? $order->items()->lockForUpdate()->get()->keyBy('id') : collect();
            $receipt = new GoodsReceipt([
                'purchase_order_id' => $order?->id, 'vendor_id' => $data['vendor_id'], 'received_on' => $date->toDateString(), 'bill_no' => $this->clip($data['bill_no'] ?? null, 60),
                'payment_mode' => $paid ? 'paid' : 'credit', 'paid_from_account_id' => $paid ? (int) $data['paid_from_account_id'] : null, 'note' => $this->clip($data['note'] ?? null),
            ]);
            $receipt->forceFill(['grn_no' => $this->inventory->docNo('GRN', $date), 'status' => 'posted', 'received_by' => $user->id, 'total' => 0, 'paid_amount' => 0])->save();

            $total = 0.0;
            foreach ($lines as $l) {
                $poItem = null;
                if ($order) {
                    $poItem = $poItems->get((int) ($l['purchase_order_item_id'] ?? 0));
                    if (! $poItem || (int) $poItem->inventory_item_id !== (int) $l['inventory_item_id']) {
                        throw ValidationException::withMessages(['lines' => __('Every line must belong to the purchase order.')]);
                    }
                    if ($l['quantity'] > $poItem->pending() + 0.0005) {
                        throw ValidationException::withMessages(['lines' => __('More than ordered: :item — :pending still to come.', ['item' => $poItem->item->name, 'pending' => rtrim(rtrim(number_format($poItem->pending(), 3, '.', ''), '0'), '.')])]);
                    }
                }
                $amount = round($l['quantity'] * $l['unit_cost'], 2);
                GoodsReceiptItem::query()->create(['goods_receipt_id' => $receipt->id, 'inventory_item_id' => $l['inventory_item_id'], 'purchase_order_item_id' => $poItem?->id,
                    'quantity' => $l['quantity'], 'unit_cost' => $l['unit_cost'], 'amount' => $amount]);
                $this->inventory->move(Item::query()->findOrFail($l['inventory_item_id']), 'receipt', $l['quantity'], $amount, $date, $receipt, $receipt->grn_no, $receipt->bill_no, $user);
                $poItem?->forceFill(['received_quantity' => round($poItem->received_quantity + $l['quantity'], 3)])->save();
                $total += $amount;
            }

            $total = round($total, 2);
            if ($total <= 0) {
                throw ValidationException::withMessages(['lines' => __('The receipt has no value — enter the unit cost of what arrived.')]);
            }
            $vendor = Vendor::query()->find($data['vendor_id']);
            $this->ledger->post($paid ? 'payment' : 'journal', $date, __('Goods received :no — :supplier', ['no' => $receipt->grn_no, 'supplier' => $vendor?->name]), [
                ['account_id' => $this->chart->id('inventory_stock'), 'debit' => $total, 'description' => $receipt->bill_no],
                ['account_id' => $paid ? (int) $data['paid_from_account_id'] : $this->chart->id('payable'), 'credit' => $total, 'description' => $vendor?->name],
            ], $receipt, $user);
            $receipt->forceFill(['total' => $total, 'paid_amount' => $paid ? $total : 0])->save();

            if ($order) {
                $this->refreshOrderStatus($order);
            }

            return $receipt;
        });
    }

    public function reverseReceipt(GoodsReceipt $receipt, string $reason, User $user): GoodsReceipt
    {
        $this->assertStatus($receipt, 'posted', __('This receipt has already been reversed.'));
        if ($receipt->payment_mode === 'credit' && $receipt->payments()->where('status', 'posted')->exists()) {
            throw ValidationException::withMessages(['status' => __('Reverse the supplier payments of this receipt first.')]);
        }

        return DB::transaction(function () use ($receipt, $reason, $user) {
            foreach ($receipt->items()->with('item')->get() as $line) {
                $this->inventory->moveExact($line->item, 'reversal', -$line->quantity, -$line->amount, now(), $receipt, $receipt->grn_no, __('Reversal — :reason', ['reason' => $reason]), $user);
                if ($line->purchase_order_item_id) {
                    $poItem = PurchaseOrderItem::query()->lockForUpdate()->find($line->purchase_order_item_id);
                    $poItem?->forceFill(['received_quantity' => max(0.0, round($poItem->received_quantity - $line->quantity, 3))])->save();
                }
            }
            $this->inventory->reverseEntries($receipt, $reason, $user);
            $receipt->forceFill(['status' => 'reversed', 'note' => mb_substr(trim($receipt->note.' '.__('Reversed: :reason', ['reason' => $reason])), 0, 255)])->save();
            if ($receipt->order) {
                $this->refreshOrderStatus($receipt->order);
            }

            return $receipt;
        });
    }

    // ───────────────────────────── supplier payments ─────────────────────────────

    public function paySupplier(GoodsReceipt $receipt, float $amount, Account $from, \DateTimeInterface|string $date, ?string $reference, User $user): SupplierPayment
    {
        $amount = round($amount, 2);
        $date = CarbonImmutable::parse($date);

        return DB::transaction(function () use ($receipt, $amount, $from, $date, $reference, $user) {
            $receipt = GoodsReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->status !== 'posted' || $receipt->payment_mode !== 'credit') {
                throw ValidationException::withMessages(['amount' => __('Only a posted receipt bought on credit can be paid.')]);
            }
            if ($amount <= 0 || $amount > $receipt->due() + 0.005) {
                throw ValidationException::withMessages(['amount' => __('Enter an amount between 0.01 and the amount due (:due).', ['due' => number_format($receipt->due(), 2)])]);
            }
            if (! Account::query()->money()->whereKey($from->id)->exists()) {
                throw ValidationException::withMessages(['account_id' => __('Choose the cash, bank or mobile-banking account the money was paid from.')]);
            }

            $payment = new SupplierPayment(['vendor_id' => $receipt->vendor_id, 'goods_receipt_id' => $receipt->id, 'date' => $date->toDateString(), 'amount' => $amount,
                'account_id' => $from->id, 'reference' => $this->clip($reference, 100)]);
            $payment->forceFill(['payment_no' => $this->inventory->docNo('SP', $date), 'status' => 'posted', 'created_by' => $user->id])->save();
            $this->ledger->post('payment', $date, __('Payment :no — :grn', ['no' => $payment->payment_no, 'grn' => $receipt->grn_no]), [
                ['account_id' => $this->chart->id('payable'), 'debit' => $amount, 'description' => $receipt->vendor?->name],
                ['account_id' => $from->id, 'credit' => $amount, 'description' => $reference],
            ], $payment, $user);
            $receipt->forceFill(['paid_amount' => round($receipt->paid_amount + $amount, 2)])->save();

            return $payment;
        });
    }

    public function reversePayment(SupplierPayment $payment, string $reason, User $user): SupplierPayment
    {
        $this->assertStatus($payment, 'posted', __('This payment has already been reversed.'));

        return DB::transaction(function () use ($payment, $reason, $user) {
            $receipt = GoodsReceipt::query()->whereKey($payment->goods_receipt_id)->lockForUpdate()->firstOrFail();
            $this->inventory->reverseEntries($payment, $reason, $user);
            $receipt->forceFill(['paid_amount' => max(0.0, round($receipt->paid_amount - $payment->amount, 2))])->save();
            $payment->forceFill(['status' => 'reversed'])->save();

            return $payment;
        });
    }

    /** What the branch owes each supplier for goods received on credit: vendor id => ['total','paid','due']. */
    public function payables(): \Illuminate\Support\Collection
    {
        return GoodsReceipt::query()->where('status', 'posted')->where('payment_mode', 'credit')
            ->selectRaw('vendor_id, SUM(total) as total, SUM(paid_amount) as paid')->groupBy('vendor_id')->get()
            ->mapWithKeys(fn ($r) => [$r->vendor_id => ['total' => (float) $r->total, 'paid' => (float) $r->paid, 'due' => round((float) $r->total - (float) $r->paid, 2)]]);
    }

    // ───────────────────────────── helpers ─────────────────────────────

    protected function refreshOrderStatus(PurchaseOrder $order): void
    {
        $items = $order->items()->get();
        $received = $items->sum('received_quantity');
        $status = $received <= 0 ? 'approved' : ($items->every(fn ($i) => $i->pending() <= 0.0005) ? 'received' : 'partial');
        $order->forceFill(['status' => $status])->save();
    }

    /** Drop blank/zero lines, normalise numbers, and merge duplicate items on purchase requests and orders. */
    protected function cleanLines(array $lines, string $costKey): \Illuminate\Support\Collection
    {
        $clean = collect($lines)->filter(fn ($l) => ! empty($l['inventory_item_id']) && (float) ($l['quantity'] ?? 0) > 0)->map(fn ($l) => [
            'inventory_item_id' => (int) $l['inventory_item_id'], 'quantity' => round((float) $l['quantity'], 3),
            $costKey => round(max(0.0, (float) ($l[$costKey] ?? 0)), 2), 'note' => $l['note'] ?? null, 'purchase_order_item_id' => $l['purchase_order_item_id'] ?? null,
        ])->values();
        if ($clean->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Add at least one item with a quantity.')]);
        }

        return $clean;
    }

    protected function assertItems(array $ids): void
    {
        $ids = array_unique($ids);
        if (Item::query()->where('is_active', true)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['lines' => __('Every line needs an active item of this branch.')]);
        }
    }

    protected function assertStatus(object $model, string $expected, string $message): void
    {
        if ($model->status !== $expected) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    protected function clip(?string $text, int $max = 255): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
