<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hostel: hostels (buildings), rooms with beds, student allocations, meal plans, attendance (roll call),
 * visitors and maintenance requests. Fees go through the normal fee invoices (category "hostel").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 8)->default('boys');             // boys | girls | mixed
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('warden_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('hostel_meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('hostel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('building', 60)->nullable();
            $table->string('floor', 30)->nullable();
            $table->string('room_no', 30);
            $table->string('type', 20)->default('shared');          // single | double | shared | dormitory
            $table->decimal('monthly_fee', 10, 2)->default(0);      // per bed
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['hostel_id', 'room_no']);
        });

        Schema::create('hostel_beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('hostel_room_id')->constrained('hostel_rooms')->cascadeOnDelete();
            $table->string('label', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hostel_room_id', 'label']);
        });

        Schema::create('hostel_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels')->restrictOnDelete();
            $table->foreignId('hostel_room_id')->constrained('hostel_rooms')->restrictOnDelete();
            $table->foreignId('hostel_bed_id')->constrained('hostel_beds')->restrictOnDelete();
            $table->foreignId('hostel_meal_plan_id')->nullable()->constrained('hostel_meal_plans')->nullOnDelete();
            $table->decimal('room_fee', 10, 2)->default(0);         // snapshot per month
            $table->decimal('meal_fee', 10, 2)->default(0);         // snapshot per month
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 12)->default('active');        // active | vacated | transferred
            $table->string('end_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['hostel_bed_id', 'status']);
        });

        Schema::create('hostel_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 10)->default('present');       // present | absent | leave | late
            $table->string('note', 255)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'date']);
        });

        Schema::create('hostel_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_name', 120);
            $table->string('relation', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('purpose', 255)->nullable();
            $table->dateTime('in_at');
            $table->dateTime('out_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hostel_id', 'in_at']);
        });

        Schema::create('hostel_maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('hostel_room_id')->nullable()->constrained('hostel_rooms')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('priority', 8)->default('normal');       // low | normal | high
            $table->string('status', 12)->default('open');         // open | in_progress | done | cancelled
            $table->decimal('cost', 10, 2)->default(0);
            $table->date('resolved_on')->nullable();
            $table->string('resolution', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hostel_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['hostel_maintenance_requests', 'hostel_visitors', 'hostel_attendance', 'hostel_allocations', 'hostel_beds', 'hostel_rooms', 'hostel_meal_plans', 'hostels'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
