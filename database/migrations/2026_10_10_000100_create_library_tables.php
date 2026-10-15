<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Library (Part 6a):
 *  library_categories      subject / genre of a book
 *  library_books           the catalogue entry (title, ISBN, authors, publisher …)
 *  library_book_copies     one row per physical copy with its own accession number and barcode
 *  library_transactions    every issue → (renewals) → return / lost, for students and employees
 *  library_reservations    queue for a title whose copies are all out
 *  library_fines           overdue / lost / damaged charges, collected into the accounts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('name_bn', 100)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('library_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 191);
            $table->string('title_bn', 191)->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('authors', 255)->nullable();
            $table->string('publisher', 150)->nullable();
            $table->string('edition', 50)->nullable();
            $table->string('language', 10)->default('bn');
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('shelf', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'title']);
            $table->index(['branch_id', 'isbn']);
        });

        Schema::create('library_book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('library_book_id')->constrained()->cascadeOnDelete();
            $table->string('accession_no', 30);
            $table->string('barcode', 60);
            $table->string('status', 12)->default('available');   // available | issued | reserved | lost | damaged | withdrawn
            $table->date('acquired_on')->nullable();
            $table->decimal('price', 10, 2)->nullable();            // null = the title's price
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'accession_no']);
            $table->unique(['branch_id', 'barcode']);
            $table->index(['library_book_id', 'status']);
        });

        Schema::create('library_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('library_book_copy_id')->constrained()->restrictOnDelete();
            $table->foreignId('library_book_id')->constrained()->restrictOnDelete();
            $table->string('member_type', 10);                     // student | employee
            $table->unsignedBigInteger('member_id');
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->unsignedTinyInteger('renewals')->default(0);
            $table->string('status', 10)->default('issued');       // issued | returned | lost
            $table->string('return_condition', 10)->nullable();    // good | damaged | lost
            $table->date('overdue_notified_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'due_on']);
            $table->index(['member_type', 'member_id', 'status']);
        });

        Schema::create('library_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('library_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_book_copy_id')->nullable()->constrained()->nullOnDelete();   // the copy held for the member
            $table->string('member_type', 10);
            $table->unsignedBigInteger('member_id');
            $table->string('status', 10)->default('waiting');      // waiting | ready | fulfilled | cancelled | expired
            $table->date('reserved_on');
            $table->date('ready_until')->nullable();
            $table->timestamps();

            $table->index(['library_book_id', 'status']);
            $table->index(['member_type', 'member_id', 'status']);
        });

        Schema::create('library_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('library_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_type', 10);
            $table->unsignedBigInteger('member_id');
            $table->string('kind', 10);                            // overdue | lost | damaged
            $table->decimal('amount', 10, 2);
            $table->string('status', 10)->default('unpaid');       // unpaid | paid | waived
            $table->string('receipt_no', 30)->nullable();
            $table->date('paid_on')->nullable();
            $table->string('method', 12)->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['member_type', 'member_id', 'status']);
            $table->unique(['branch_id', 'receipt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_fines');
        Schema::dropIfExists('library_reservations');
        Schema::dropIfExists('library_transactions');
        Schema::dropIfExists('library_book_copies');
        Schema::dropIfExists('library_books');
        Schema::dropIfExists('library_categories');
    }
};
