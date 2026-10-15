<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transport: vehicles, drivers & helpers, routes with stops, student allocations (monthly fee),
 * maintenance, fuel and driver attendance. Fees are billed through the normal fee invoices (category "transport").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('registration_no', 40);
            $table->string('name', 100)->nullable();
            $table->string('type', 12)->default('bus');             // bus | minibus | microbus | van | other
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->string('fuel_type', 12)->default('diesel');     // diesel | petrol | octane | cng | electric
            $table->date('insurance_expiry')->nullable();
            $table->date('fitness_expiry')->nullable();
            $table->date('tax_token_expiry')->nullable();
            $table->date('route_permit_expiry')->nullable();
            $table->string('tracking_url', 255)->nullable();        // optional live-tracking page of the GPS provider (shown to guardians)
            $table->string('status', 12)->default('active');        // active | maintenance | retired
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'registration_no']);
        });

        Schema::create('transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('role', 10)->default('driver');          // driver | helper
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('license_no', 50)->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('nid_number', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('transport_vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('transport_drivers')->nullOnDelete();
            $table->foreignId('helper_id')->nullable()->constrained('transport_drivers')->nullOnDelete();
            $table->decimal('monthly_fee', 10, 2)->default(0);      // default for stops that have no fee of their own
            $table->time('morning_start')->nullable();
            $table->time('afternoon_start')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('transport_route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->time('pickup_time')->nullable();
            $table->time('drop_time')->nullable();
            $table->decimal('monthly_fee', 10, 2)->nullable();      // null = the route's fee
            $table->timestamps();
        });

        Schema::create('transport_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained('transport_routes')->restrictOnDelete();
            $table->foreignId('transport_stop_id')->constrained('transport_stops')->restrictOnDelete();
            $table->decimal('monthly_fee', 10, 2);                  // snapshot at allocation, editable
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 10)->default('active');        // active | ended
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('transport_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('transport_vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->date('date');
            $table->string('kind', 12)->default('service');         // service | repair | inspection | tyres | other
            $table->string('description', 255);
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('vendor', 120)->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->date('next_due_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transport_fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('transport_vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->foreignId('transport_driver_id')->nullable()->constrained('transport_drivers')->nullOnDelete();
            $table->date('date');
            $table->decimal('litres', 8, 2);
            $table->decimal('total_cost', 10, 2);
            $table->unsignedInteger('odometer')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transport_driver_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('transport_driver_id')->constrained('transport_drivers')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 10)->default('present');       // present | absent | leave
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['transport_driver_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['transport_driver_attendance', 'transport_fuel_logs', 'transport_maintenance', 'transport_allocations', 'transport_stops', 'transport_routes', 'transport_drivers', 'transport_vehicles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
