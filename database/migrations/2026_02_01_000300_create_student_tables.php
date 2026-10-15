<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student Information System.
 *  students            permanent record (never hard-deleted; status tracks lifecycle)
 *  guardians           parents/guardians (optionally linked to a parent-portal user)
 *  guardian_student    relationship (father/mother/…), primary & emergency flags
 *  student_enrollments one row per student per academic session → class/section/roll history
 *  student_documents   private uploads (birth certificate, previous TC, photos …)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // parent portal account
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('phone', 20);
            $table->string('alt_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('nid_number', 30)->nullable(); // restricted field — never exposed via API
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'phone']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // student portal account
            $table->string('student_code', 30);     // generated, e.g. BR01-260001
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('gender', 10);           // male | female | other
            $table->date('date_of_birth');
            $table->string('birth_registration_no', 30)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('religion', 30)->nullable();
            $table->string('nationality', 50)->default('Bangladeshi');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('previous_school')->nullable();
            $table->date('admission_date');
            $table->string('status', 20)->default('active'); // active | transferred | withdrawn | graduated | suspended
            $table->date('status_changed_on')->nullable();
            $table->text('medical_notes')->nullable();      // encrypted cast, students.view-health only
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'student_code']);
            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'name']);
            $table->index('birth_registration_no');
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 30); // father | mother | brother | sister | uncle | aunt | grandparent | other
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
            $table->index('guardian_id');
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('roll_no');
            $table->string('status', 20)->default('enrolled'); // enrolled | promoted | repeated | transferred | withdrawn | completed
            $table->date('enrolled_on');
            $table->date('left_on')->nullable();
            $table->foreignId('previous_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->timestamps();

            // Master prompt §8: no duplicate enrollment in the same academic session.
            $table->unique(['student_id', 'academic_session_id'], 'enrollment_student_session_unique');
            $table->unique(['section_id', 'academic_session_id', 'roll_no'], 'enrollment_section_roll_unique');
            $table->index(['branch_id', 'academic_session_id', 'school_class_id', 'section_id'], 'enrollment_lookup_index');
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40); // birth_certificate | photo | previous_tc | marksheet | other
            $table->string('title');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('students');
        Schema::dropIfExists('guardians');
    }
};
