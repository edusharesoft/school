<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap-safe, concurrency-safe counters per branch (student IDs, admission numbers,
 * later invoice/receipt numbers). Rows are locked with SELECT … FOR UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);           // student_code | admission_no | invoice_no …
            $table->string('period', 10)->default(''); // e.g. "2026" for yearly counters
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'name', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
