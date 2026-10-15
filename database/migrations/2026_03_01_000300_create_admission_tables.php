<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admissions, transfers, certificates, staff branch assignments (Part 3B-2).
 *  admission_campaigns        one admission round per class & session (deadline, seats, test, fee, online form settings)
 *  admission_applications     online / offline applications → test marks → merit / waiting list → admitted student
 *  admission_documents        private uploads attached to an application (copied to the student on admission)
 *  student_transfers          branch → branch transfer requests inside one institution
 *  certificates               transfer / character / study certificates with verifiable serial numbers
 *  staff_branch_assignments   an employee of one branch also working in another branch of the institution
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');                       // application deadline (inclusive)
            $table->unsignedSmallInteger('seats');
            $table->boolean('is_online')->default(true);   // shown on the public admission page
            $table->boolean('has_test')->default(false);
            $table->dateTime('test_at')->nullable();
            $table->string('test_venue')->nullable();
            $table->decimal('test_total_marks', 6, 2)->nullable();
            $table->decimal('test_pass_marks', 6, 2)->nullable();
            $table->unsignedTinyInteger('min_age')->nullable();   // age on the session start date
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->decimal('admission_fee', 10, 2)->default(0);
            $table->boolean('fee_before_admission')->default(false);
            $table->json('required_documents')->nullable();       // e.g. ["birth_certificate","photo"]
            $table->json('form_settings')->nullable();            // optional fields shown / required on the form
            $table->boolean('merit_published')->default(false);
            $table->string('status', 20)->default('draft');       // draft | open | closed
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'academic_session_id', 'school_class_id'], 'campaign_lookup_index');
        });

        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_campaign_id')->constrained()->restrictOnDelete();
            $table->string('application_no', 30);           // generated, e.g. BR01-A26-00042
            $table->string('source', 10)->default('online'); // online | offline
            $table->string('status', 20)->default('submitted'); // submitted | verified | selected | waiting | rejected | admitted | cancelled
            // applicant
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth');
            $table->string('birth_registration_no', 30)->nullable();
            $table->string('religion', 30)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('phone', 20);                     // contact mobile used for status lookup
            $table->string('email')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('previous_school')->nullable();
            $table->string('previous_class', 60)->nullable();
            $table->string('previous_result', 60)->nullable();
            $table->string('photo_path')->nullable();
            // guardian
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('guardian_name');
            $table->string('guardian_phone', 20);
            $table->string('guardian_relation', 30);
            $table->string('guardian_occupation', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            // test & decision
            $table->decimal('test_marks', 6, 2)->nullable();
            $table->boolean('test_absent')->default(false);
            $table->unsignedSmallInteger('merit_position')->nullable();
            $table->unsignedSmallInteger('waiting_position')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            // admission fee hook (invoiced & collected through Part 4 finance)
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->string('fee_status', 10)->default('unpaid'); // unpaid | paid | waived
            $table->string('fee_reference', 60)->nullable();
            $table->timestamp('fee_recorded_at')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'application_no']);
            $table->index(['admission_campaign_id', 'status']);
            $table->index(['branch_id', 'phone']);
            $table->index(['admission_campaign_id', 'birth_registration_no'], 'application_birth_reg_index');
        });

        Schema::create('admission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->index('admission_application_id');
        });

        Schema::create('student_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();      // record in the source branch
            $table->foreignId('new_student_id')->nullable()->constrained('students')->nullOnDelete(); // record created in the target branch
            $table->string('status', 20)->default('pending'); // pending | accepted | rejected | cancelled
            $table->date('requested_on');
            $table->string('reason', 1000);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['from_branch_id', 'status']);
            $table->index(['to_branch_id', 'status']);
            $table->index('student_id');
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);               // transfer | character | study
            $table->string('serial_no', 40);          // e.g. BR01-TC-26-0003
            $table->string('verification_code', 20);  // printed on the certificate, used by the public verify page
            $table->date('issued_on');
            $table->json('data');                     // snapshot: class, session, roll, conduct, reason for leaving …
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'serial_no']);
            $table->unique('verification_code');
            $table->index(['student_id', 'type']);
        });

        Schema::create('staff_branch_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();   // home-branch employee record
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();     // additional branch
            $table->string('duty', 191)->nullable();   // e.g. "Physics classes XI–XII, Sun & Tue"
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'ends_on']);
            $table->index(['employee_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_branch_assignments');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('student_transfers');
        Schema::dropIfExists('admission_documents');
        Schema::dropIfExists('admission_applications');
        Schema::dropIfExists('admission_campaigns');
    }
};
