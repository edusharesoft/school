<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks & results (Part 3C-2).
 *  exam_marks        one row per student per paper: component marks {"theory":55,"practical":20} or absent
 *  mark_corrections  change requests for marks after marks entry closed (request → approve / reject)
 *  exam_results      processed result per student per exam: totals, GPA, grade, positions, subject snapshot
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->json('marks')->nullable();
            $table->boolean('is_absent')->default(false);
            $table->decimal('total', 6, 2)->nullable();   // null while a component is missing
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['examination_subject_id', 'student_id']);
            $table->index(['examination_id', 'student_id']);
        });

        Schema::create('mark_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_mark_id')->constrained()->cascadeOnDelete();
            $table->json('old_marks')->nullable();
            $table->boolean('old_absent')->default(false);
            $table->json('new_marks')->nullable();
            $table->boolean('new_absent')->default(false);
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('study_group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('roll_no')->nullable();
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->decimal('full_marks', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->decimal('gpa_without_optional', 4, 2)->nullable();
            $table->string('grade', 5)->nullable();
            $table->string('status', 20);                // pass | fail | incomplete
            $table->unsignedSmallInteger('failed_subjects')->default(0);
            $table->unsignedInteger('class_position')->nullable();
            $table->unsignedInteger('section_position')->nullable();
            $table->decimal('attendance_pct', 5, 2)->nullable();
            $table->json('subjects');                    // per-paper snapshot used by mark sheets & tabulation
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->unique(['examination_id', 'student_id']);
            $table->index(['examination_id', 'school_class_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('mark_corrections');
        Schema::dropIfExists('exam_marks');
    }
};
