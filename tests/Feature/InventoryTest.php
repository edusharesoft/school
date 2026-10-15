<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Finance\Vendor;
use App\Models\Inventory\Category;
use App\Models\Inventory\GoodsReceipt;
use App\Models\Inventory\Item;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Requisition;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAudit;
use App\Models\Inventory\StockIssue;
use App\Models\Inventory\StockTransaction;
use App\Models\Inventory\SupplierPayment;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Inventory: stock at average cost, buying and paying suppliers, issues, adjustments, audits — always in step with the books. */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
    }

    protected function world(): array
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');
        $buyer = $this->branchUser($branch, 'lab-manager');          // inventory view / create / update — no approve
        $cat = $this->inBranch($branch, fn () => Category::query()->create(['name' => 'Stationery', 'kind' => 'general', 'is_active' => true]));
        $paper = $this->item($branch, $cat, 'A4 paper', 'ream');
        $vendor = $this->inBranch($branch, fn () => Vendor::query()->create(['name' => 'Rahim Stores', 'is_active' => true]));
        $cash = $this->inBranch($branch, fn () => app(ChartOfAccounts::class)->id('cash'));

        return compact('branch', 'admin', 'buyer', 'cat', 'paper', 'vendor', 'cash');
    }

    protected function item(Branch $branch, Category $cat, string $name, string $unit = 'pcs', string $type = 'consumable', float $reorder = 0): Item
    {
        return $this->inBranch($branch, fn () => app(InventoryService::class)->createItem([
            'name' => $name, 'inventory_category_id' => $cat->id, 'item_type' => $type, 'unit' => $unit, 'reorder_level' => $reorder, 'is_active' => true,
        ]));
    }

    protected function url(Branch $branch, string $path = ''): string
    {
        return $this->branchUrl($branch, 'inventory'.($path === '' ? '' : '/'.$path));
    }

    protected function reload(Branch $branch, $model)
    {
        return $this->inBranch($branch, fn () => $model->fresh());
    }

    /** Run something inside the branch context with the services. */
    protected function svc(Branch $branch, \Closure $fn): mixed
    {
        return $this->inBranch($branch, fn () => $fn(app(InventoryService::class), app(ProcurementService::class)));
    }

    protected function receive(array $w, Item $item, float $qty, float $cost, string $mode = 'credit', ?User $user = null): GoodsReceipt
    {
        return $this->svc($w['branch'], fn ($inv, $buy) => $buy->receive([
            'vendor_id' => $w['vendor']->id, 'received_on' => '2026-03-10', 'payment_mode' => $mode, 'paid_from_account_id' => $mode === 'paid' ? $w['cash'] : null,
        ], [['inventory_item_id' => $item->id, 'quantity' => $qty, 'unit_cost' => $cost]], $user ?? $w['admin']));
    }

    protected function balance(Branch $branch, string $key): float
    {
        return $this->inBranch($branch, fn () => app(LedgerService::class)->balance(app(ChartOfAccounts::class)->get($key)));
    }

    /** The stock sub-ledger (items) always equals the "Inventory stock" account, and every entry balances. */
    protected function assertStockMatchesBooks(Branch $branch): void
    {
        $this->inBranch($branch, function () use ($branch) {
            $items = round((float) Item::query()->withTrashed()->sum('stock_value'), 2);
            $this->assertEqualsWithDelta($items, $this->balance($branch, 'inventory_stock'), 0.001, 'items value = inventory stock account');
            $this->assertEqualsWithDelta($items, round((float) StockTransaction::query()->sum('amount'), 2), 0.001, 'items value = sum of the stock card');
            $this->assertEqualsWithDelta((float) \App\Models\Finance\JournalLine::query()->sum('debit'), (float) \App\Models\Finance\JournalLine::query()->sum('credit'), 0.001);
        });
    }

    public function test_items_codes_and_opening_stock_over_http(): void
    {
        $w = $this->world();
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'items'), [
            'name' => 'Whiteboard marker', 'inventory_category_id' => $w['cat']->id, 'item_type' => 'consumable', 'unit' => 'pcs', 'reorder_level' => 10,
            'opening_quantity' => 40, 'opening_unit_cost' => 25,
        ])->assertSessionHasNoErrors();

        $item = $this->inBranch($w['branch'], fn () => Item::query()->where('name', 'Whiteboard marker')->sole());
        $this->assertSame('IT-000002', $item->code);                       // IT-000001 is the paper from world()
        $this->assertEquals(40, $item->quantity_on_hand);
        $this->assertEquals(1000, $item->stock_value);
        $this->assertEquals(1000, $this->balance($w['branch'], 'inventory_stock'));
        $this->assertEquals(1000, $this->balance($w['branch'], 'opening_balance'));
        $this->assertSame('opening', $this->inBranch($w['branch'], fn () => StockAdjustment::query()->sole()->reason));
        $this->assertStockMatchesBooks($w['branch']);

        // duplicate name, foreign category, bad type
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'items'), ['name' => 'Whiteboard marker', 'inventory_category_id' => $w['cat']->id, 'item_type' => 'consumable', 'unit' => 'pcs'])->assertSessionHasErrors('name');
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'items'), ['name' => 'X', 'inventory_category_id' => 9999, 'item_type' => 'consumable', 'unit' => 'pcs'])->assertSessionHasErrors('inventory_category_id');
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'items'), ['name' => 'Y', 'inventory_category_id' => $w['cat']->id, 'item_type' => 'weird', 'unit' => 'pcs'])->assertSessionHasErrors('item_type');

        // an item with movements cannot be deleted
        $this->actingOnBranch($w['admin'], $w['branch'])->delete($this->url($w['branch'], "items/{$item->id}"))->assertSessionHas('warning');
        $this->assertNotNull($this->inBranch($w['branch'], fn () => Item::query()->find($item->id)));
    }

    public function test_opening_stock_needs_the_approve_permission(): void
    {
        $w = $this->world();
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'items'), [
            'name' => 'Beaker', 'inventory_category_id' => $w['cat']->id, 'item_type' => 'durable', 'unit' => 'pcs', 'opening_quantity' => 5, 'opening_unit_cost' => 100,
        ])->assertSessionHas('warning');
        $item = $this->inBranch($w['branch'], fn () => Item::query()->where('name', 'Beaker')->sole());
        $this->assertEquals(0, $item->quantity_on_hand);
    }

    public function test_credit_receipts_average_cost_and_supplier_payments(): void
    {
        $w = $this->world();
        $this->receive($w, $w['paper'], 10, 10);
        $second = $this->receive($w, $w['paper'], 10, 20);

        $paper = $this->reload($w['branch'], $w['paper']);
        $this->assertEquals(20, $paper->quantity_on_hand);
        $this->assertEquals(300, $paper->stock_value);
        $this->assertEquals(15, $paper->averageCost());
        $this->assertEquals(300, $this->balance($w['branch'], 'inventory_stock'));
        $this->assertEquals(300, $this->balance($w['branch'], 'payable'));
        $this->assertMatchesRegularExpression('/-GRN26-00002$/', $second->grn_no);
        $this->assertStockMatchesBooks($w['branch']);

        // pay part of the second bill, then the rest; cannot overpay
        $pay = fn (float $amount) => $this->svc($w['branch'], fn ($i, $buy) => $buy->paySupplier($this->reload($w['branch'], $second), $amount, \App\Models\Finance\Account::query()->find($w['cash']), '2026-03-12', 'chq 1', $w['admin']));
        $payment = $pay(120);
        $this->assertEquals(80, $this->inBranch($w['branch'], fn () => $this->reload($w['branch'], $second)->due()));
        try {
            $pay(80.01);
            $this->fail('overpaying must fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
        $pay(80);
        $this->assertEquals(0, $this->inBranch($w['branch'], fn () => $this->reload($w['branch'], $second)->due()));
        $this->assertEquals(100, $this->balance($w['branch'], 'payable'));         // the first bill is still owed
        $this->assertEquals(-200, $this->balance($w['branch'], 'cash'));

        // the receipt cannot be reversed while a payment is attached; reverse the payment, then the receipt
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->reverseReceipt($this->reload($w['branch'], $second), 'wrong bill', $w['admin'])), 'status');
        $this->svc($w['branch'], fn ($i, $buy) => $buy->reversePayment($this->reload($w['branch'], $payment), 'bounced', $w['admin']));
        $this->assertEquals(220, $this->balance($w['branch'], 'payable'));       // 100 still owed on bill 2... plus the 120 that was reopened
        $this->assertEquals(-80, $this->balance($w['branch'], 'cash'));
        $this->assertStockMatchesBooks($w['branch']);
    }

    public function test_paid_receipt_posts_to_cash_and_reversal_restores_everything(): void
    {
        $w = $this->world();
        $receipt = $this->receive($w, $w['paper'], 4, 50, 'paid');
        $this->assertEquals(-200, $this->balance($w['branch'], 'cash'));
        $this->assertEquals(0, $this->balance($w['branch'], 'payable'));
        $this->assertEquals(0, $this->reload($w['branch'], $receipt)->due());

        // money account is mandatory when paid now
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->receive(['vendor_id' => $w['vendor']->id, 'received_on' => '2026-03-10', 'payment_mode' => 'paid'],
            [['inventory_item_id' => $w['paper']->id, 'quantity' => 1, 'unit_cost' => 5]], $w['admin'])), 'paid_from_account_id');
        // a receipt without value is refused
        $this->expectValidation(fn () => $this->receive($w, $w['paper'], 3, 0), 'lines');

        $this->svc($w['branch'], fn ($i, $buy) => $buy->reverseReceipt($receipt, 'duplicate', $w['admin']));
        $this->assertEquals(0, $this->balance($w['branch'], 'cash'));
        $this->assertEquals(0, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);
        $this->assertSame('reversed', $this->reload($w['branch'], $receipt)->status);
        $this->assertStockMatchesBooks($w['branch']);
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->reverseReceipt($this->reload($w['branch'], $receipt), 'again', $w['admin'])), 'status');
    }

    public function test_requisition_to_order_to_partial_and_full_receipt(): void
    {
        $w = $this->world();
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'requisitions'), [
            'date' => '2026-03-14', 'purpose' => 'Term exam printing',
            'lines' => [['inventory_item_id' => $w['paper']->id, 'quantity' => 30, 'estimated_unit_cost' => 480], ['inventory_item_id' => '', 'quantity' => '']],
        ])->assertSessionHasNoErrors();
        $req = $this->inBranch($w['branch'], fn () => Requisition::query()->with('items')->sole());
        $this->assertSame('pending', $req->status);
        $this->assertCount(1, $req->items);                                    // the blank line is dropped
        $this->assertMatchesRegularExpression('/-PR26-00001$/', $req->requisition_no);

        // the requester cannot approve it, even with the permission; an approver can
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->approveRequisition($req, $w['buyer'])), 'status');
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], "requisitions/{$req->id}/approve"))->assertForbidden();
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], "requisitions/{$req->id}/approve"))->assertSessionHasNoErrors();
        $this->assertSame('approved', $this->reload($w['branch'], $req)->status);

        // order from the request: drafted by the buyer, approved by the admin
        $this->actingOnBranch($w['buyer'], $w['branch'])->get($this->url($w['branch'], "orders/create?requisition={$req->id}"))->assertOk()->assertSee($req->requisition_no, false);
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'orders'), [
            'vendor_id' => $w['vendor']->id, 'inventory_requisition_id' => $req->id, 'order_date' => '2026-03-15',
            'lines' => [['inventory_item_id' => $w['paper']->id, 'quantity' => 30, 'unit_cost' => 450]],
        ])->assertSessionHasNoErrors();
        $order = $this->inBranch($w['branch'], fn () => PurchaseOrder::query()->with('items')->sole());
        $this->assertSame('draft', $order->status);
        $this->assertEquals(13500, $order->total);
        $this->assertSame('ordered', $this->reload($w['branch'], $req)->status);

        // a draft cannot be received; the maker cannot approve
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->receive(['purchase_order_id' => $order->id, 'received_on' => '2026-03-15', 'payment_mode' => 'credit'],
            [['inventory_item_id' => $w['paper']->id, 'quantity' => 1, 'unit_cost' => 450, 'purchase_order_item_id' => $order->items[0]->id]], $w['admin'])), 'purchase_order_id');
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->approveOrder($order, $w['buyer'])), 'status');
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], "orders/{$order->id}/approve"))->assertSessionHasNoErrors();
        $this->assertSame('approved', $this->reload($w['branch'], $order)->status);

        $line = $order->items[0];
        $receive = fn (float $qty) => $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'receipts'), [
            'purchase_order_id' => $order->id, 'received_on' => '2026-03-15', 'payment_mode' => 'credit', 'bill_no' => 'B-77',
            'lines' => [['inventory_item_id' => $w['paper']->id, 'purchase_order_item_id' => $line->id, 'quantity' => $qty, 'unit_cost' => 450]],
        ]);
        $receive(20)->assertSessionHasNoErrors();
        $this->assertSame('partial', $this->reload($w['branch'], $order)->status);
        $this->assertEquals(20, $this->reload($w['branch'], $line)->received_quantity);

        $receive(11)->assertSessionHasErrors('lines');                         // only 10 still to come
        $this->assertEquals(20, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);

        $receive(10)->assertSessionHasNoErrors();
        $this->assertSame('received', $this->reload($w['branch'], $order)->status);
        $this->assertEquals(13500, $this->balance($w['branch'], 'payable'));
        $this->assertStockMatchesBooks($w['branch']);

        // reversing the last receipt re-opens the order
        $last = $this->inBranch($w['branch'], fn () => GoodsReceipt::query()->latest('id')->first());
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], "receipts/{$last->id}/reverse"), ['reason' => 'sent back'])->assertSessionHasNoErrors();
        $this->assertSame('partial', $this->reload($w['branch'], $order)->status);
        $this->assertEquals(10, $this->reload($w['branch'], $line)->pending());
        $this->assertStockMatchesBooks($w['branch']);

        // an order with receipts cannot be cancelled
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->cancelOrder($this->reload($w['branch'], $order))), 'status');
    }

    public function test_cancelling_an_order_reopens_the_request(): void
    {
        $w = $this->world();
        $req = $this->svc($w['branch'], fn ($i, $buy) => $buy->createRequisition(['date' => '2026-03-14', 'purpose' => 'x'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 2]], $w['buyer']));
        $this->svc($w['branch'], fn ($i, $buy) => $buy->approveRequisition($req, $w['admin']));
        $order = $this->svc($w['branch'], fn ($i, $buy) => $buy->createOrder(['vendor_id' => $w['vendor']->id, 'order_date' => '2026-03-15'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 2, 'unit_cost' => 5]], $w['buyer'], $this->reload($w['branch'], $req)));
        $this->assertSame('ordered', $this->reload($w['branch'], $req)->status);
        $this->svc($w['branch'], fn ($i, $buy) => $buy->cancelOrder($order));
        $this->assertSame('cancelled', $this->reload($w['branch'], $order)->status);
        $this->assertSame('approved', $this->reload($w['branch'], $req)->status);
        // a request that is not approved cannot become an order
        $other = $this->svc($w['branch'], fn ($i, $buy) => $buy->createRequisition(['date' => '2026-03-14', 'purpose' => 'y'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 1]], $w['buyer']));
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->createOrder(['vendor_id' => $w['vendor']->id, 'order_date' => '2026-03-15'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 1, 'unit_cost' => 5]], $w['buyer'], $other)), 'requisition');
    }

    public function test_issue_takes_average_cost_and_charges_the_expense(): void
    {
        $w = $this->world();
        $this->receive($w, $w['paper'], 10, 10);
        $this->receive($w, $w['paper'], 10, 20);                              // 20 reams, 300, average 15

        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'issues'), [
            'date' => '2026-03-15', 'recipient' => 'Exam office', 'purpose' => 'Mid-term',
            'lines' => [['inventory_item_id' => $w['paper']->id, 'quantity' => 5], ['inventory_item_id' => $w['paper']->id, 'quantity' => 1]],   // duplicate lines add up
        ])->assertSessionHasNoErrors();
        $issue = $this->inBranch($w['branch'], fn () => StockIssue::query()->with('items')->sole());
        $this->assertEquals(90, $issue->total);                                // 6 × 15
        $this->assertCount(1, $issue->items);
        $this->assertEquals(14, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);
        $this->assertEquals(210, $this->reload($w['branch'], $w['paper'])->stock_value);
        $this->assertEquals(90, $this->balance($w['branch'], 'stock_consumed'));
        $this->assertStockMatchesBooks($w['branch']);

        // the last units take whatever value is left — nothing is stranded
        $this->svc($w['branch'], fn ($inv) => $inv->issue(['date' => '2026-03-15', 'recipient' => 'Office'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 14]], $w['buyer']));
        $this->assertEquals(0, $this->reload($w['branch'], $w['paper'])->stock_value);
        $this->assertEquals(300, $this->balance($w['branch'], 'stock_consumed'));
        $this->assertStockMatchesBooks($w['branch']);

        // not enough stock, nobody to issue to, durable equipment
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'issues'), ['date' => '2026-03-15', 'recipient' => 'X', 'lines' => [['inventory_item_id' => $w['paper']->id, 'quantity' => 1]]])->assertSessionHasErrors('quantity');
        $this->receive($w, $w['paper'], 2, 10);
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'issues'), ['date' => '2026-03-15', 'lines' => [['inventory_item_id' => $w['paper']->id, 'quantity' => 1]]])->assertSessionHasErrors('recipient');
        $scope = $this->item($w['branch'], $w['cat'], 'Microscope', 'pcs', 'durable');
        $this->receive($w, $scope, 1, 5000);
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'issues'), ['date' => '2026-03-15', 'recipient' => 'Lab', 'lines' => [['inventory_item_id' => $scope->id, 'quantity' => 1]]])->assertSessionHasErrors('lines');
        $this->assertEquals(1, $this->reload($w['branch'], $scope)->quantity_on_hand);
        $this->assertStockMatchesBooks($w['branch']);
    }

    public function test_reversing_an_issue_returns_the_goods_at_the_same_value(): void
    {
        $w = $this->world();
        $this->receive($w, $w['paper'], 10, 12);
        $issue = $this->svc($w['branch'], fn ($inv) => $inv->issue(['date' => '2026-03-15', 'department_id' => null, 'recipient' => 'Office'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 4]], $w['buyer']));
        $this->assertEquals(72, $this->reload($w['branch'], $w['paper'])->stock_value);               // 4 × 12 = 48 taken out
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], "issues/{$issue->id}/reverse"), ['reason' => 'returned'])->assertForbidden();
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], "issues/{$issue->id}/reverse"), ['reason' => 'returned'])->assertSessionHasNoErrors();
        $this->assertEquals(10, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);
        $this->assertEquals(120, $this->reload($w['branch'], $w['paper'])->stock_value);
        $this->assertEquals(0, $this->balance($w['branch'], 'stock_consumed'));
        $this->assertSame('reversed', $this->reload($w['branch'], $issue)->status);
        $this->assertStockMatchesBooks($w['branch']);
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($inv) => $inv->reverseIssue($this->reload($w['branch'], $issue), 'again', $w['admin'])), 'status');
    }

    public function test_a_receipt_cannot_be_reversed_after_its_goods_were_used(): void
    {
        $w = $this->world();
        $receipt = $this->receive($w, $w['paper'], 10, 10);
        $this->svc($w['branch'], fn ($inv) => $inv->issue(['date' => '2026-03-15', 'recipient' => 'Office'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 7]], $w['buyer']));
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($i, $buy) => $buy->reverseReceipt($receipt, 'oops', $w['admin'])), 'quantity');
        $this->assertSame('posted', $this->reload($w['branch'], $receipt)->status);
        $this->assertEquals(3, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);
        $this->assertStockMatchesBooks($w['branch']);
    }

    public function test_adjustments_value_losses_at_average_and_gains_at_the_given_cost(): void
    {
        $w = $this->world();
        $this->receive($w, $w['paper'], 10, 10);

        $adj = $this->svc($w['branch'], fn ($inv) => $inv->adjust(['date' => '2026-03-15', 'reason' => 'damage', 'note' => 'water'], [['inventory_item_id' => $w['paper']->id, 'quantity' => -3]], $w['admin']));
        $this->assertEquals(-30, $adj->net_value);
        $this->assertEquals(30, $this->balance($w['branch'], 'stock_adjustment'));
        $this->assertEquals(70, $this->reload($w['branch'], $w['paper'])->stock_value);

        $found = $this->svc($w['branch'], fn ($inv) => $inv->adjust(['date' => '2026-03-15', 'reason' => 'found'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 2, 'unit_cost' => 11]], $w['admin']));
        $this->assertEquals(22, $found->net_value);
        $this->assertEquals(8, $this->balance($w['branch'], 'stock_adjustment'));         // 30 loss − 22 gain
        $this->assertEquals(9, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);
        $this->assertStockMatchesBooks($w['branch']);

        // more than there is, zero lines, duplicate items, unknown reason
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($inv) => $inv->adjust(['date' => '2026-03-15', 'reason' => 'loss'], [['inventory_item_id' => $w['paper']->id, 'quantity' => -50]], $w['admin'])), 'quantity');
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($inv) => $inv->adjust(['date' => '2026-03-15', 'reason' => 'loss'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 0]], $w['admin'])), 'lines');
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($inv) => $inv->adjust(['date' => '2026-03-15', 'reason' => 'loss'], [['inventory_item_id' => $w['paper']->id, 'quantity' => -1], ['inventory_item_id' => $w['paper']->id, 'quantity' => -1]], $w['admin'])), 'lines');
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($inv) => $inv->adjust(['date' => '2026-03-15', 'reason' => 'whim'], [['inventory_item_id' => $w['paper']->id, 'quantity' => -1]], $w['admin'])), 'reason');

        // reversal puts the loss back exactly
        $this->svc($w['branch'], fn ($inv) => $inv->reverseAdjustment($adj, 'counted again', $w['admin']));
        $this->assertEquals(12, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);
        $this->assertEquals(-22, $this->balance($w['branch'], 'stock_adjustment'));
        $this->assertStockMatchesBooks($w['branch']);

        // only people who may approve can adjust
        $this->actingOnBranch($w['buyer'], $w['branch'])->get($this->url($w['branch'], 'adjustments/create'))->assertForbidden();
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'adjustments'), ['date' => '2026-03-15', 'reason' => 'loss', 'lines' => [['inventory_item_id' => $w['paper']->id, 'quantity' => -1]]])->assertForbidden();
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'adjustments'), ['date' => '2026-03-15', 'reason' => 'loss', 'lines' => [['inventory_item_id' => $w['paper']->id, 'quantity' => -1]]])->assertSessionHasNoErrors();
    }

    public function test_audit_counts_become_one_recount_adjustment(): void
    {
        $w = $this->world();
        $pens = $this->item($w['branch'], $w['cat'], 'Pen', 'pcs');
        $this->receive($w, $w['paper'], 10, 10);
        $this->receive($w, $pens, 100, 2);

        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], 'audits'), ['title' => 'Annual count', 'date' => '2026-03-15'])->assertSessionHasNoErrors();
        $audit = $this->inBranch($w['branch'], fn () => StockAudit::query()->with('items')->sole());
        $this->assertCount(2, $audit->items);

        $paperLine = $audit->items->firstWhere('inventory_item_id', $w['paper']->id);
        $penLine = $audit->items->firstWhere('inventory_item_id', $pens->id);
        $this->actingOnBranch($w['buyer'], $w['branch'])->put($this->url($w['branch'], "audits/{$audit->id}/counts"), ['counts' => [
            $paperLine->id => ['counted' => 8, 'note' => '2 reams missing'], $penLine->id => ['counted' => ''],     // pens not counted yet
        ]])->assertSessionHasNoErrors();

        // completing needs the approve permission; nothing is posted until then
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], "audits/{$audit->id}/complete"))->assertForbidden();
        $this->assertEquals(10, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], "audits/{$audit->id}/complete"))->assertSessionHasNoErrors();

        $this->assertSame('completed', $this->reload($w['branch'], $audit)->status);
        $this->assertEquals(8, $this->reload($w['branch'], $w['paper'])->quantity_on_hand);
        $this->assertEquals(100, $this->reload($w['branch'], $pens)->quantity_on_hand);              // uncounted lines are left alone
        $adj = $this->inBranch($w['branch'], fn () => StockAdjustment::query()->with('items')->sole());
        $this->assertSame('recount', $adj->reason);
        $this->assertSame($audit->id, $adj->stock_audit_id);
        $this->assertEquals(-20, $adj->net_value);
        $this->assertEquals(20, $this->balance($w['branch'], 'stock_adjustment'));
        $this->assertStockMatchesBooks($w['branch']);

        // a closed audit cannot be counted or completed again
        $this->actingOnBranch($w['buyer'], $w['branch'])->put($this->url($w['branch'], "audits/{$audit->id}/counts"), ['counts' => [$paperLine->id => ['counted' => 1]]])->assertSessionHasErrors('status');
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], "audits/{$audit->id}/complete"))->assertSessionHasErrors('status');
    }

    public function test_an_audit_without_counts_cannot_be_completed_and_can_be_cancelled(): void
    {
        $w = $this->world();
        $audit = $this->svc($w['branch'], fn ($inv) => $inv->startAudit(['title' => 'Spot check', 'date' => '2026-03-15'], $w['admin']));
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($inv) => $inv->completeAudit($audit, $w['admin'])), 'counts');
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], "audits/{$audit->id}/cancel"))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->reload($w['branch'], $audit)->status);
        // an audit of a category with no active item
        $empty = $this->inBranch($w['branch'], fn () => Category::query()->create(['name' => 'Empty', 'kind' => 'general', 'is_active' => true]));
        $this->expectValidation(fn () => $this->svc($w['branch'], fn ($inv) => $inv->startAudit(['title' => 'x', 'date' => '2026-03-15', 'inventory_category_id' => $empty->id], $w['admin'])), 'inventory_category_id');
    }

    public function test_category_expense_account_is_charged_on_issue(): void
    {
        $w = $this->world();
        $lab = $this->inBranch($w['branch'], function () {
            $chart = app(ChartOfAccounts::class);
            $chart->ensure();

            return Category::query()->create(['name' => 'Chemicals', 'kind' => 'laboratory', 'expense_account_id' => $chart->id('stationery'), 'is_active' => true]);
        });
        $acid = $this->item($w['branch'], $lab, 'Acid', 'litre');
        $this->receive($w, $acid, 5, 100);
        $this->svc($w['branch'], fn ($inv) => $inv->issue(['date' => '2026-03-15', 'recipient' => 'Chemistry lab'], [['inventory_item_id' => $acid->id, 'quantity' => 2]], $w['buyer']));
        $this->assertEquals(200, $this->balance($w['branch'], 'stationery'));
        $this->assertEquals(0, $this->balance($w['branch'], 'stock_consumed'));
        $this->assertStockMatchesBooks($w['branch']);

        // the category form only accepts expense accounts of this branch
        $income = $this->inBranch($w['branch'], fn () => app(ChartOfAccounts::class)->id('fee_income'));
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'categories'), ['name' => 'Bad', 'kind' => 'general', 'expense_account_id' => $income])->assertSessionHasErrors('expense_account_id');
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'categories'), ['name' => 'Sports', 'kind' => 'general'])->assertSessionHasNoErrors();
        // a category with items cannot be deleted
        $this->actingOnBranch($w['admin'], $w['branch'])->delete($this->url($w['branch'], "categories/{$lab->id}"))->assertSessionHas('warning');
    }

    public function test_screens_render_and_permissions_hold(): void
    {
        $w = $this->world();
        $this->receive($w, $w['paper'], 3, 10);                                 // below a reorder level of 5
        $this->inBranch($w['branch'], fn () => $w['paper']->update(['reorder_level' => 5]));
        $req = $this->svc($w['branch'], fn ($i, $buy) => $buy->createRequisition(['date' => '2026-03-14', 'purpose' => 'More paper'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 2, 'estimated_unit_cost' => 10]], $w['buyer']));
        $order = $this->svc($w['branch'], fn ($i, $buy) => $buy->createOrder(['vendor_id' => $w['vendor']->id, 'order_date' => '2026-03-15'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 2, 'unit_cost' => 10]], $w['buyer']));
        $receipt = GoodsReceipt::query()->withoutGlobalScopes()->latest('id')->first();
        $audit = $this->svc($w['branch'], fn ($inv) => $inv->startAudit(['title' => 'Count', 'date' => '2026-03-15'], $w['admin']));
        $issue = $this->svc($w['branch'], fn ($inv) => $inv->issue(['date' => '2026-03-15', 'recipient' => 'Office'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 1]], $w['admin']));
        $adj = $this->svc($w['branch'], fn ($inv) => $inv->adjust(['date' => '2026-03-15', 'reason' => 'found'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 1, 'unit_cost' => 10]], $w['admin']));

        $pages = [
            '' => 'Running low', 'items' => 'A4 paper', 'items/create' => 'New item', "items/{$w['paper']->id}" => 'Stock card', "items/{$w['paper']->id}/edit" => 'Edit item',
            'categories' => 'Stationery', 'suppliers' => 'Rahim Stores', "suppliers?edit={$w['vendor']->id}" => 'Edit supplier',
            'requisitions' => 'More paper', 'requisitions/create' => 'New purchase request', "requisitions/{$req->id}" => 'Estimated total',
            'orders' => $order->po_no, 'orders/create' => 'New purchase order', "orders/{$order->id}" => 'Approve order', "orders/{$order->id}/print" => 'Purchase order',
            'receipts' => $receipt->grn_no, 'receipts/create' => 'Receive goods', "receipts/{$receipt->id}" => 'Payments to the supplier',
            'issues' => $issue->issue_no, 'issues/create' => 'Issue stock', "issues/{$issue->id}" => 'Reverse',
            'adjustments' => $adj->adjustment_no, 'adjustments/create' => 'New stock adjustment', "adjustments/{$adj->id}" => 'Net value',
            'audits' => $audit->audit_no, "audits/{$audit->id}" => 'Save counts',
            'reports/stock' => 'Value by category', 'reports/consumption' => 'By department', 'reports/purchases' => 'By supplier',
        ];
        foreach ($pages as $path => $text) {
            $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w['branch'], $path))->assertOk()->assertSee($text, false);
        }
        $this->actingOnBranch($w['buyer'], $w['branch'])->get($this->url($w['branch'], "orders/{$order->id}"))->assertOk()->assertDontSee('Approve order', false);
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w['branch'], 'items?status=low'))->assertOk()->assertSee('A4 paper');
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w['branch'], 'receipts?due=1'))->assertOk()->assertSee($receipt->grn_no);

        // teachers have no inventory at all
        $teacher = $this->branchUser($w['branch'], 'teacher');
        foreach (['', 'items', 'orders', 'reports/stock'] as $path) {
            $this->actingOnBranch($teacher, $w['branch'])->get($this->url($w['branch'], $path))->assertForbidden();
        }
        // a lab manager can look and request but not approve or reverse
        $this->actingOnBranch($w['buyer'], $w['branch'])->get($this->url($w['branch'], 'items'))->assertOk();
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], "orders/{$order->id}/approve"))->assertForbidden();
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], "receipts/{$receipt->id}/reverse"), ['reason' => 'x'])->assertForbidden();
        $this->actingOnBranch($w['buyer'], $w['branch'])->post($this->url($w['branch'], "receipts/{$receipt->id}/pay"), ['amount' => 1, 'date' => '2026-03-15', 'account_id' => $w['cash']])->assertForbidden();
    }

    public function test_other_branches_data_is_invisible_and_unusable(): void
    {
        $w = $this->world();
        $other = $this->makeBranch($w['branch']->institution);
        $otherAdmin = $this->branchUser($other, 'branch-admin');
        $otherCat = $this->inBranch($other, fn () => Category::query()->create(['name' => 'Other', 'kind' => 'general', 'is_active' => true]));
        $foreign = $this->item($other, $otherCat, 'Foreign item');
        $this->receive($w, $w['paper'], 5, 10);

        // route binding
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w['branch'], "items/{$foreign->id}"))->assertNotFound();
        $this->actingOnBranch($otherAdmin, $other)->get($this->url($other, "items/{$w['paper']->id}"))->assertNotFound();

        // posted ids
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'issues'), ['date' => '2026-03-15', 'recipient' => 'X', 'lines' => [['inventory_item_id' => $foreign->id, 'quantity' => 1]]])->assertSessionHasErrors('lines.0.inventory_item_id');
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w['branch'], 'items'), ['name' => 'Z', 'inventory_category_id' => $otherCat->id, 'item_type' => 'consumable', 'unit' => 'pcs'])->assertSessionHasErrors('inventory_category_id');

        // lists and totals
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w['branch'], 'items'))->assertOk()->assertSee('A4 paper')->assertDontSee('Foreign item');
        $this->assertEquals(1, $this->inBranch($other, fn () => Item::query()->count()));
        $this->assertEquals(0, $this->balance($other, 'inventory_stock'));
        $this->assertStockMatchesBooks($w['branch']);
        $this->assertStockMatchesBooks($other);

        // the same item name may exist in two branches; item codes restart per branch
        $same = $this->item($other, $otherCat, 'A4 paper');
        $this->assertSame('IT-000002', $same->code);
        $this->assertSame('IT-000001', $w['paper']->code);
    }

    public function test_central_view_lists_every_branch(): void
    {
        $w = $this->world();
        $other = $this->makeBranch($w['branch']->institution);
        $otherCat = $this->inBranch($other, fn () => Category::query()->create(['name' => 'Other', 'kind' => 'general', 'is_active' => true]));
        $thing = $this->item($other, $otherCat, 'Chalk box', 'box', 'consumable', 5);
        $otherVendor = $this->inBranch($other, fn () => Vendor::query()->create(['name' => 'Karim Traders', 'is_active' => true]));
        $this->inBranch($other, fn () => app(ProcurementService::class)->receive(['vendor_id' => $otherVendor->id, 'received_on' => '2026-03-10', 'payment_mode' => 'credit'],
            [['inventory_item_id' => $thing->id, 'quantity' => 4, 'unit_cost' => 30]], $this->branchUser($other, 'branch-admin')));
        $this->receive($w, $w['paper'], 10, 10);

        $root = User::factory()->platform()->role('super-admin')->create();
        $this->actingCentral($root)->get($this->centralUrl('super-admin/inventory'))->assertOk()
            ->assertSee($w['branch']->name)->assertSee($other->name)->assertSee('100.00')->assertSee('120.00');
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->centralUrl('super-admin/inventory'))->assertRedirectContains('/login');

        $rows = $this->inBranch($w['branch'], fn () => app(\App\Services\Inventory\InventoryReportService::class)->consolidated([$w['branch']->id, $other->id]));
        $this->assertEquals(220, $rows['total']['value']);
        $this->assertEquals(1, $rows['total']['low']);                         // 4 chalk boxes ≤ reorder level 5
        $this->assertEquals(220, $rows['total']['due']);
    }

    public function test_reports_figures(): void
    {
        $w = $this->world();
        $this->receive($w, $w['paper'], 10, 10);
        $this->svc($w['branch'], fn ($inv) => $inv->issue(['date' => '2026-03-12', 'recipient' => 'Office'], [['inventory_item_id' => $w['paper']->id, 'quantity' => 4]], $w['admin']));
        $report = app(\App\Services\Inventory\InventoryReportService::class);

        $card = $this->inBranch($w['branch'], fn () => $report->stockCard($w['paper'], \Carbon\CarbonImmutable::parse('2026-03-11'), \Carbon\CarbonImmutable::parse('2026-03-15')));
        $this->assertEquals(10, $card['openingQuantity']);                       // received on the 10th
        $this->assertEquals(100, $card['openingValue']);
        $this->assertEquals(4, $card['out']);
        $this->assertEquals(6, $card['closingQuantity']);
        $this->assertEquals(60, $card['closingValue']);

        $use = $this->inBranch($w['branch'], fn () => $report->consumption(\Carbon\CarbonImmutable::parse('2026-03-01'), \Carbon\CarbonImmutable::parse('2026-03-31')));
        $this->assertEquals(40, $use['total']);
        $this->assertSame(1, $use['issues']);

        $buy = $this->inBranch($w['branch'], fn () => $report->purchases(\Carbon\CarbonImmutable::parse('2026-03-01'), \Carbon\CarbonImmutable::parse('2026-03-31')));
        $this->assertEquals(100, $buy['total']);
        $this->assertEquals(100, $buy['bySupplier'][0]['due']);

        $ov = $this->inBranch($w['branch'], fn () => $report->overview());
        $this->assertEquals(60, $ov['value']);
        $this->assertEquals(100, $ov['payable']);
    }

    /** The closure must throw a ValidationException that has an error on $key. */
    protected function expectValidation(\Closure $fn, string $key): void
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), 'errors: '.json_encode($e->errors()));

            return;
        }
        $this->fail("Expected a validation error on [$key].");
    }
}
