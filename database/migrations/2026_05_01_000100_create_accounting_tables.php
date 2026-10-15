<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry accounting foundation (Part 4). Every branch keeps its own books; consolidated reports add branches up.
 *  financial_years   per branch; postings are refused in a closed year
 *  accounts          chart of accounts (asset / liability / equity / income / expense), groups, cash & bank accounts,
 *                    system accounts identified by system_key (fees_receivable, student_advance, …)
 *  journal_entries   vouchers; posted entries are never edited — corrections are reversal entries
 *  journal_lines     debit / credit lines, optional student sub-ledger
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 30);                  // "2026-27"
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'starts_on']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('name_bn', 120)->nullable();
            $table->string('type', 12);                  // asset | liability | equity | income | expense
            $table->boolean('is_group')->default(false);
            $table->string('system_key', 40)->nullable(); // fees_receivable, student_advance, cash, bank, fee_discount, late_fee, …
            $table->string('kind', 12)->nullable();       // cash | bank | mobile — accounts money can be received into / paid from
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
            $table->unique(['branch_id', 'system_key']);
            $table->index(['branch_id', 'type']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->string('entry_no', 40);
            $table->date('date');
            $table->string('type', 20);                  // invoice | receipt | payment | refund | journal | contra | reversal
            $table->string('narration', 255);
            $table->nullableMorphs('source');            // FeeInvoice, FeePayment, Expense, Payroll …
            $table->decimal('amount', 14, 2);            // total debit (= total credit)
            $table->string('status', 12)->default('posted'); // posted | reversed
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'entry_no']);
            $table->index(['branch_id', 'date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->date('date');                        // copy of the entry date for fast ledgers

            $table->index(['account_id', 'date']);
            $table->index(['branch_id', 'date']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('financial_years');
    }
};
