<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examinations (Part 3C).
 *  grading_scales         configurable grade bands (percentage → letter grade & grade point) and GPA rules
 *  examinations           an exam of a session (half-yearly, annual, class test …) with its grading scale and status
 *  examination_subjects   per class & subject: date/time, components (theory / practical / continuous assessment)
 *                         with full & pass marks, optional-subject flag
 * Marks, results, seat plans and admit cards are added in Part 3C-2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->json('grades');                       // [{grade, min, max, point, remark}] on percentage, highest first
            $table->decimal('max_gpa', 4, 2)->default(5.00);
            $table->decimal('optional_base_point', 4, 2)->default(2.00); // 4th subject: only points above this are added
            $table->unsignedTinyInteger('gpa_decimals')->default(2);
            $table->string('rounding', 10)->default('round'); // round | floor (GPA)
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_default']);
        });

        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grading_scale_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('term');   // class_test | term | half_yearly | annual | pre_test | test | model
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('min_attendance')->nullable(); // % needed for an admit card (attendance eligibility)
            $table->string('status', 20)->default('draft'); // draft | scheduled | marks_entry | processing | approved | published
            $table->text('instructions')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'academic_session_id', 'status']);
        });

        Schema::create('examination_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_group_id')->nullable()->constrained()->nullOnDelete(); // null = all groups
            $table->date('exam_date')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->json('components');                  // {"theory":{"full":70,"pass":23},"practical":{…},"ca":{…}}
            $table->decimal('full_marks', 6, 2);         // sum of component full marks
            $table->decimal('pass_marks', 6, 2)->default(0); // overall pass mark (on the total)
            $table->boolean('is_optional')->default(false);   // 4th subject (GPA bonus rule)
            $table->boolean('counts_in_gpa')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['examination_id', 'school_class_id', 'subject_id', 'study_group_id'], 'exam_subject_unique');
            $table->index(['examination_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_subjects');
        Schema::dropIfExists('examinations');
        Schema::dropIfExists('grading_scales');
    }
};
