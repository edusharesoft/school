<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asset register (Part 6c-2): fixed assets, allocation, maintenance & calibration, depreciation, disposal and physical verification.
 *
 *  assets                      one row per physical asset (tag AS-000001); accumulated_depreciation is the running total of asset_depreciations
 *  asset_allocations           who / where an asset is (one open allocation per asset)
 *  asset_maintenance_plans     recurring tasks (service, calibration, safety check) with the next due date
 *  asset_maintenance_logs      work orders: preventive / calibration / inspection / repair — cost is recorded for reporting only
 *  asset_depreciation_runs     one posting per run:   Dr Depreciation / Cr Accumulated depreciation
 *  asset_depreciations         one row per asset and month (immutable; a mistake is reversed through its run)
 *  asset_disposals             sale / scrap / donation / loss:  Dr cash + Dr accumulated depreciation (+ Dr loss) / Cr Fixed assets (+ Cr gain)
 *  asset_audits(+_items)       physical verification of the register
 *
 * Accounting: acquisition Dr Fixed assets / Cr cash-bank | Inventory stock | Other income (gift) | Opening balance equity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 12)->default('general');                 // general | laboratory
            $table->string('depreciation_method', 20)->default('straight_line'); // none | straight_line | declining_balance (defaults for new assets)
            $table->unsignedSmallInteger('useful_life_months')->default(60);
            $table->decimal('salvage_percent', 5, 2)->default(0);
            $table->decimal('declining_rate', 5, 2)->default(0);           // % per year, reducing balance
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);                                      // AS-000001 (also the barcode)
            $table->string('name', 150);
            $table->string('name_bn', 150)->nullable();
            $table->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->string('serial_no', 80)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('acquisition_type', 10)->default('purchase');    // purchase | stock | donation | opening
            $table->string('bill_no', 60)->nullable();
            $table->date('acquired_on');
            $table->date('in_service_on');
            $table->decimal('cost', 14, 2);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->string('depreciation_method', 20)->default('straight_line');
            $table->unsignedSmallInteger('useful_life_months')->default(0);
            $table->decimal('declining_rate', 5, 2)->default(0);
            $table->string('depreciation_from', 7)->nullable();             // first month (YYYY-MM) to charge
            $table->string('depreciated_through', 7)->nullable();           // last month (YYYY-MM) charged
            $table->unsignedSmallInteger('months_depreciated')->default(0);
            $table->decimal('accumulated_depreciation', 14, 2)->default(0);
            $table->string('status', 10)->default('in_stock');              // in_stock | in_use | disposed | voided
            $table->boolean('is_under_repair')->default(false);
            $table->boolean('is_missing')->default(false);
            $table->string('condition', 10)->default('good');               // new | good | fair | poor | damaged
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('location', 100)->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->string('safety_notes', 255)->nullable();                // laboratory equipment
            $table->string('description', 255)->nullable();
            $table->date('last_verified_on')->nullable();
            $table->date('disposed_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'asset_category_id']);
            $table->index(['branch_id', 'warranty_expires_on']);
        });

        Schema::create('asset_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('assignee', 150)->nullable();                    // free text: "Science club"
            $table->date('allocated_on');
            $table->date('expected_return_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->string('return_condition', 10)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'asset_id', 'returned_on']);
        });

        Schema::create('asset_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('kind', 12)->default('preventive');              // preventive | calibration | inspection | safety
            $table->unsignedSmallInteger('interval_months');
            $table->date('start_on');
            $table->date('last_done_on')->nullable();
            $table->date('next_due_on');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();   // responsible person (gets the reminder)
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->date('last_notified_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'is_active', 'next_due_on']);
        });

        Schema::create('asset_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('log_no', 40);
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('asset_maintenance_plan_id')->nullable()->constrained('asset_maintenance_plans')->nullOnDelete();
            $table->string('kind', 12)->default('repair');                  // preventive | calibration | inspection | safety | repair | upgrade
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('reported_on');
            $table->date('started_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->string('status', 12)->default('open');                  // open | in_progress | completed | cancelled
            $table->boolean('takes_offline')->default(false);               // asset is out of service while the job is open
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->decimal('cost', 14, 2)->default(0);                     // recorded for reporting only — not posted to the books
            $table->string('reference', 100)->nullable();                   // job card / calibration certificate number
            $table->string('outcome', 255)->nullable();
            $table->string('condition_after', 10)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'log_no']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('asset_depreciation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('run_no', 40);
            $table->string('period', 7);                                    // charged up to this month (YYYY-MM)
            $table->date('date');
            $table->decimal('total', 14, 2)->default(0);
            $table->unsignedInteger('asset_count')->default(0);
            $table->string('status', 10)->default('posted');                // posted | reversed
            $table->string('kind', 10)->default('monthly');                 // monthly | disposal (catch-up before a disposal)
            $table->string('note', 255)->nullable();
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'run_no']);
            $table->index(['branch_id', 'period']);
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('asset_depreciation_run_id')->constrained('asset_depreciation_runs')->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('amount', 14, 2);
            $table->decimal('accumulated_after', 14, 2);
            $table->decimal('book_value_after', 14, 2);
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();
            $table->index(['branch_id', 'asset_id', 'period']);
            $table->index(['branch_id', 'period']);
        });

        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('disposal_no', 40);
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('type', 10);                                     // sale | scrap | donated | lost
            $table->date('date');
            $table->decimal('cost', 14, 2);
            $table->decimal('accumulated', 14, 2);
            $table->decimal('book_value', 14, 2);
            $table->decimal('proceeds', 14, 2)->default(0);
            $table->decimal('gain_loss', 14, 2)->default(0);                // positive = gain, negative = loss
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();   // proceeds received into
            $table->string('buyer', 150)->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('status', 10)->default('posted');                // posted | reversed
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'disposal_no']);
            $table->index(['branch_id', 'asset_id']);
        });

        Schema::create('asset_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('audit_no', 40);
            $table->string('title', 150);
            $table->date('date');
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('status', 10)->default('open');                  // open | completed | cancelled
            $table->string('note', 255)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'audit_no']);
        });

        Schema::create('asset_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_audit_id')->constrained('asset_audits')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('expected_location', 150)->nullable();
            $table->string('result', 10)->nullable();                       // found | missing | damaged (null = not checked yet)
            $table->string('found_location', 150)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['asset_audit_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        foreach (['asset_audit_items', 'asset_audits', 'asset_disposals', 'asset_depreciations', 'asset_depreciation_runs', 'asset_maintenance_logs',
            'asset_maintenance_plans', 'asset_allocations', 'assets', 'asset_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
