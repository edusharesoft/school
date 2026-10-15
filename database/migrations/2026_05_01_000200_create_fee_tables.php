<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student fees (Part 4).
 *  fee_heads               configurable charges (admission, tuition, session, exam, lab, library, transport, hostel …)
 *  fee_structures          amount per session × class (× group) × head and the months it is billed in
 *  student_fee_adjustments student-specific discounts / scholarships / waivers / sibling discounts / extra fees
 *  fee_invoices (+items)   monthly (generated, idempotent) and one-off invoices; balance = total − paid
 *  fee_payments            receipts (cash / bank / mobile banking …); unallocated part = advance credit
 *  fee_allocations         which invoice a payment (or advance credit) settled
 *  fee_refunds             refunds paid out of a student's advance credit
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('name_bn', 100)->nullable();
            $table->string('code', 20);
            $table->string('category', 20);              // admission | tuition | session | exam | lab | library | transport | hostel | development | other
            $table->foreignId('income_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_head_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->json('months');                      // [1,2,…,12] months (1 = January) the head is billed in
            $table->timestamps();

            $table->unique(['academic_session_id', 'school_class_id', 'study_group_id', 'fee_head_id'], 'fee_structure_unique');
        });

        Schema::create('student_fee_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_head_id')->nullable()->constrained()->nullOnDelete(); // null = every head (discounts only)
            $table->string('type', 20);                  // scholarship | waiver | sibling | staff_child | merit | discount | extra_fee
            $table->string('mode', 10)->default('percent'); // percent | fixed (discounts); extra_fee is always fixed
            $table->decimal('value', 12, 2);
            $table->json('months')->nullable();          // extra_fee: billing months; discounts: null = every month
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'academic_session_id']);
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('invoice_no', 40);
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7)->nullable();     // YYYY-MM for monthly invoices
            $table->string('title', 150);
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('waived', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->string('status', 12)->default('unpaid'); // unpaid | partial | paid | cancelled
            $table->string('generated_key', 60)->nullable(); // "auto:<student>:<period>" — prevents duplicate generation
            $table->timestamp('late_fee_applied_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'invoice_no']);
            $table->unique(['branch_id', 'generated_key']);
            $table->index(['branch_id', 'status', 'due_date']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('fee_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 150);
            $table->decimal('amount', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('discount_note', 150)->nullable();
            $table->boolean('is_late_fee')->default(false);
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('receipt_no', 40);
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->decimal('unallocated', 12, 2)->default(0); // remaining advance credit of this payment
            $table->string('method', 12);                // cash | bank | cheque | bkash | nagad | rocket | card | online
            $table->foreignId('account_id')->constrained()->restrictOnDelete(); // cash / bank account debited
            $table->string('reference', 100)->nullable(); // cheque no., transaction id …
            $table->string('notes', 255)->nullable();
            $table->string('status', 12)->default('completed'); // completed | reversed
            $table->string('reverse_reason', 255)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'receipt_no']);
            $table->index(['branch_id', 'date']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('fee_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->boolean('from_credit')->default(false); // applied later from advance credit
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete(); // credit application entry
            $table->timestamps();

            $table->index('fee_invoice_id');
        });

        Schema::create('fee_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('refund_no', 40);
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('method', 12);
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->string('reason', 255);
            $table->json('sources');                     // [{payment_id, amount}] advance credit consumed
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'refund_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_refunds');
        Schema::dropIfExists('fee_allocations');
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_invoice_items');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('student_fee_adjustments');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_heads');
    }
};
