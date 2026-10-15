<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff & HR (Part 3B).
 *  designations          per-branch job titles (teaching / non-teaching)
 *  employees             permanent staff record (teachers + other employees), optional login account
 *  employee_documents    private uploads (CV, certificates, appointment letter …)
 *  employment_histories  append-only career events (joined, promoted, department change, salary revision, separation)
 *  leave_types           per-branch leave catalogue with yearly allowance
 *  leave_applications    applications with approval workflow (approved leave is written into staff attendance)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('name_bn', 100)->nullable();
            $table->string('code', 30);
            $table->string('category', 20)->default('teaching'); // teaching | non_teaching
            $table->unsignedSmallInteger('rank')->default(100);   // display / seniority order (lower = senior)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'category']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // login account (teacher / staff portal)
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 30);            // generated, e.g. BR01-E0007
            $table->string('employee_type', 20);            // teacher | staff
            $table->string('employment_type', 20);          // permanent | contract | part_time | guest | probation
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('nid_number', 30)->nullable();   // restricted (employees.view-salary), never exposed via API
            $table->string('blood_group', 5)->nullable();
            $table->string('religion', 30)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('joining_date');
            $table->text('qualification')->nullable();
            $table->decimal('experience_years', 4, 1)->default(0);
            $table->decimal('basic_salary', 12, 2)->nullable(); // restricted (employees.view-salary); full structure in Part 4
            $table->string('status', 20)->default('active'); // active | resigned | terminated | retired | suspended
            $table->date('status_changed_on')->nullable();
            $table->string('separation_reason', 500)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'employee_code']);
            $table->unique(['branch_id', 'user_id']);
            $table->index(['branch_id', 'status', 'employee_type']);
            $table->index(['branch_id', 'name']);
            $table->index(['branch_id', 'phone']);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40); // cv | certificate | nid | appointment_letter | experience_letter | other
            $table->string('title');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'type']);
        });

        Schema::create('employment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30); // joined | designation_changed | department_changed | type_changed | salary_revised | resigned | terminated | retired | suspended | reinstated
            $table->date('effective_on');
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_on']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('name_bn', 80)->nullable();
            $table->string('code', 20);
            $table->unsignedSmallInteger('days_per_year')->default(0); // 0 = unlimited (e.g. leave without pay)
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1);            // counted working days (half day = 0.5)
            $table->boolean('is_half_day')->default(false);
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['employee_id', 'from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('employment_histories');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('designations');
    }
};
