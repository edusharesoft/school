<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses, payroll, budgets and bank reconciliation (Part 4-2).
 *  vendors             suppliers / payees
 *  expenses            expense vouchers: request → approve (posted) → paid (if bought on credit); reversal instead of edits
 *  employee_salaries   salary structure per employee (basic, allowances, deductions)
 *  payroll_runs(+items) monthly salary sheet: draft → approved (posted to expense / payables) → paid (posted to cash / bank)
 *  budgets             budget per account and financial year
 *  journal_lines.reconciled_on   bank reconciliation (date the line cleared on the bank statement)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('bin', 30)->nullable();               // VAT BIN / TIN
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('expense_no', 40);
            $table->date('date');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();          // expense account
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 14, 2);
            $table->string('payment_mode', 10)->default('paid');  // paid (from cash / bank now) | credit (payable, paid later)
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('reference', 100)->nullable();         // bill / cheque / transaction no.
            $table->string('attachment_path')->nullable();        // private disk
            $table->string('status', 12)->default('pending');     // pending | approved | paid | rejected | reversed
            $table->string('review_note', 255)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'expense_no']);
            $table->index(['branch_id', 'status', 'date']);
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic', 12, 2);
            $table->json('allowances')->nullable();   // [{name, amount}] house rent, medical, conveyance …
            $table->json('deductions')->nullable();   // [{name, amount}] provident fund, tax, loan …
            $table->string('payment_method', 10)->default('bank'); // bank | cash | mobile
            $table->string('account_number', 40)->nullable();
            $table->date('effective_from');
            $table->timestamps();

            $table->unique('employee_id');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('period', 7);               // YYYY-MM
            $table->string('status', 12)->default('draft'); // draft | approved | paid | cancelled
            $table->unsignedInteger('employees')->default(0);
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('deductions', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('paid_on')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'period']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('basic', 12, 2);
            $table->json('allowances')->nullable();
            $table->decimal('gross', 12, 2);
            $table->json('deductions')->nullable();
            $table->unsignedSmallInteger('unpaid_days')->default(0);   // absent (not on leave) + leave without pay
            $table->decimal('unpaid_deduction', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['financial_year_id', 'account_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->date('reconciled_on')->nullable()->after('date');
            $table->foreignId('reconciled_by')->nullable()->after('reconciled_on')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn('reconciled_on');
        });
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('vendors');
    }
};
