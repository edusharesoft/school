<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory (Part 6c-1): catalogue and stock, purchase requests, purchase orders, goods receiving, supplier payments,
 * stock issues, stock adjustments and stock audits. Suppliers are the existing `vendors` of the accounts module.
 *
 *  inventory_items.quantity_on_hand / stock_value   running balance (moving weighted-average cost); every change is a stock_transactions row
 *  stock_transactions                                immutable stock card (receipt | issue | adjustment | reversal), signed quantity and value
 *  goods_receipts                                    Dr Inventory stock / Cr Accounts payable (or cash / bank when paid at once)
 *  supplier_payments                                 Dr Accounts payable / Cr cash / bank
 *  stock_issues                                      Dr consumption expense / Cr Inventory stock
 *  stock_adjustments                                 shortage Dr Stock adjustment / Cr Inventory stock; surplus the other way round
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 12)->default('general');                 // general | laboratory
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // charged when items are issued
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);                                      // IT-000001
            $table->string('name', 150);
            $table->string('name_bn', 150)->nullable();
            $table->foreignId('inventory_category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->string('item_type', 10)->default('consumable');         // consumable | durable
            $table->string('unit', 20)->default('pcs');
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('stock_value', 14, 2)->default(0);
            $table->string('location', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'inventory_category_id']);
        });

        Schema::create('inventory_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('requisition_no', 40);
            $table->date('date');
            $table->date('needed_by')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('purpose', 255);
            $table->string('status', 12)->default('pending');               // pending | approved | rejected | ordered | cancelled
            $table->string('review_note', 255)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'requisition_no']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_requisition_id')->constrained('inventory_requisitions')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('estimated_unit_cost', 14, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('po_no', 40);
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('inventory_requisition_id')->nullable()->constrained('inventory_requisitions')->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_on')->nullable();
            $table->string('status', 12)->default('draft');                 // draft | approved | partial | received | cancelled
            $table->decimal('total', 14, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'po_no']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('grn_no', 40);
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->date('received_on');
            $table->string('bill_no', 60)->nullable();                      // supplier challan / invoice number
            $table->string('payment_mode', 8)->default('credit');           // credit (payable) | paid (cash / bank now)
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 10)->default('posted');                // posted | reversed
            $table->string('note', 255)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'grn_no']);
            $table->index(['branch_id', 'vendor_id']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('payment_no', 40);
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 14, 2);
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();   // paid from (cash / bank / mobile)
            $table->string('reference', 100)->nullable();
            $table->string('status', 10)->default('posted');                // posted | reversed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'payment_no']);
        });

        Schema::create('stock_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('issue_no', 40);
            $table->date('date');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('recipient', 150)->nullable();                   // free text: "Science lab", "Class 6 teacher"
            $table->string('purpose', 255)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('status', 10)->default('issued');                // issued | reversed
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'issue_no']);
        });

        Schema::create('stock_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_issue_id')->constrained('stock_issues')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('amount', 14, 2)->default(0);                   // value taken out of stock (average cost)
            $table->timestamps();
        });

        Schema::create('stock_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('audit_no', 40);
            $table->date('date');
            $table->string('title', 150);
            $table->foreignId('inventory_category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('status', 10)->default('open');                  // open | completed | cancelled
            $table->string('note', 255)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'audit_no']);
        });

        Schema::create('stock_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('expected_quantity', 14, 3);                    // book stock when the audit was opened
            $table->decimal('counted_quantity', 14, 3)->nullable();         // null = not counted yet
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['stock_audit_id', 'inventory_item_id']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_no', 40);
            $table->date('date');
            $table->string('reason', 12);                                   // opening | damage | loss | expired | found | recount | other
            $table->foreignId('stock_audit_id')->nullable()->constrained('stock_audits')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->decimal('net_value', 14, 2)->default(0);                // gains − losses
            $table->string('status', 10)->default('posted');                // posted | reversed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'adjustment_no']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);                             // signed: + adds stock, − removes it
            $table->decimal('amount', 14, 2);                               // signed value
            $table->timestamps();
        });

        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->date('date');
            $table->string('type', 12);                                     // receipt | issue | adjustment | reversal
            $table->decimal('quantity', 14, 3);                             // signed
            $table->decimal('amount', 14, 2);                               // signed
            $table->decimal('balance_quantity', 14, 3);
            $table->decimal('balance_value', 14, 2);
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inventory_item_id', 'date', 'id']);
            $table->index(['branch_id', 'date']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        foreach (['stock_transactions', 'stock_adjustment_items', 'stock_adjustments', 'stock_audit_items', 'stock_audits', 'stock_issue_items', 'stock_issues',
            'supplier_payments', 'goods_receipt_items', 'goods_receipts', 'purchase_order_items', 'purchase_orders', 'inventory_requisition_items',
            'inventory_requisitions', 'inventory_items', 'inventory_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
