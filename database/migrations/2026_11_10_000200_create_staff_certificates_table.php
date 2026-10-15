<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Experience and service certificates of staff (Part 6e). Like student certificates, each has a serial number, a public
 * verification code and a snapshot of the facts it states, so later edits to the employee never change an issued certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);                       // experience | service
            $table->string('serial_no', 40);                  // e.g. BR01-EX-26-0001
            $table->string('verification_code', 20);
            $table->date('issued_on');
            $table->json('data');                             // snapshot: name, designation, department, dates, conduct, remarks …
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'serial_no']);
            $table->unique('verification_code');
            $table->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_certificates');
    }
};
