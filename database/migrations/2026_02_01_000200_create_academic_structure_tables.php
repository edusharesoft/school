<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable Bangladeshi school & college structure (no hard-coded board or curriculum).
 *  academic_years      institution-wide calendar year (e.g. "2026", "2026-27")
 *  academic_sessions   per-branch session inside a year (enrollments hang off this)
 *  terms               half-yearly / annual / semester periods inside a session
 *  school_classes      Play … Ten, XI, XII, Honours year … (per branch)
 *  sections            A / B / Morning … per class
 *  study_groups        Science / Humanities / Business Studies …
 *  subjects, class_subjects (subject combinations per class & group), rooms
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('name', 30);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['institution_id', 'name']);
            $table->index(['institution_id', 'is_current']);
        });

        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->string('status', 20)->default('open'); // open | closed
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
            $table->index(['branch_id', 'is_current']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['academic_session_id', 'name']);
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('name_bn', 60)->nullable();
            $table->string('code', 20);
            $table->string('level', 30)->default('secondary'); // pre_primary | primary | junior_secondary | secondary | higher_secondary | tertiary
            $table->unsignedSmallInteger('numeric_order')->default(0); // promotion order: 6 → 7 → …
            $table->boolean('has_groups')->default(false);             // e.g. IX–XII need Science/Humanities/Business
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'numeric_order']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('code', 20);
            $table->string('building', 60)->nullable();
            $table->string('type', 30)->default('classroom'); // classroom | lab | hall | library | other
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('shift', 20)->nullable(); // morning | day | evening
            $table->unsignedSmallInteger('capacity')->default(50);
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_class_id', 'name']);
            $table->index(['branch_id', 'school_class_id']);
        });

        Schema::create('study_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('name_bn', 60)->nullable();
            $table->string('code', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('name_bn', 100)->nullable();
            $table->string('code', 20);           // board / internal subject code, e.g. 101
            $table->string('type', 20)->default('theory'); // theory | practical | theory_practical
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_group_id')->nullable()->constrained()->nullOnDelete(); // null = all groups
            $table->string('category', 20)->default('compulsory'); // compulsory | optional (4th subject) | elective
            $table->unsignedSmallInteger('full_marks')->default(100);
            $table->unsignedSmallInteger('pass_marks')->default(33);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // NULL group allowed once per class/subject through the app-level check (MySQL treats NULLs as distinct).
            $table->unique(['school_class_id', 'subject_id', 'study_group_id'], 'class_subject_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('study_groups');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_sessions');
        Schema::dropIfExists('academic_years');
    }
};
