<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID card register (Part 6d): every card printed for a student or a staff member gets a number, a validity date and a
 * public verification code (printed as a QR on the back). A lost card is marked so that scanning it shows "not valid".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('holder_type', 10);                                  // student | employee
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('card_no', 30);                                      // e.g. IDS-000012 / IDE-000003
            $table->string('verification_code', 16);
            $table->date('issued_on');
            $table->date('valid_until');
            $table->string('status', 12)->default('active');                    // active | lost | revoked | replaced
            $table->string('status_reason', 255)->nullable();
            $table->unsignedSmallInteger('printed_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'card_no']);
            $table->unique('verification_code');
            $table->index(['branch_id', 'holder_type', 'status']);
            $table->index('student_id');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_cards');
    }
};
