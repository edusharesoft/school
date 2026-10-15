<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learning management, part 1 (Part 5-3):
 *  learning_materials       documents, video lessons, recorded lessons and links shared with a class / section
 *  assignments              homework, assignments and projects (due date, marks, submission mode, instructions)
 *  assignment_submissions   one row per student per assignment: text / file, lateness, marks and teacher feedback
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);                       // document | video | recorded_lesson | link
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('url', 500)->nullable();           // video / recorded lesson / link
            $table->string('file_path', 255)->nullable();     // private disk
            $table->string('file_name', 191)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'school_class_id', 'section_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12)->default('homework');          // homework | assignment | project
            $table->string('title', 191);
            $table->text('instructions')->nullable();
            $table->timestamp('due_at');
            $table->decimal('max_marks', 6, 2)->nullable();            // null = not graded (feedback only)
            $table->string('submission_mode', 8)->default('either');   // file | text | either | none (done in class / on paper)
            $table->boolean('allow_late')->default(false);
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 191)->nullable();
            $table->string('status', 10)->default('draft');           // draft | published | closed
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'due_at']);
            $table->index(['branch_id', 'school_class_id', 'section_id']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('text')->nullable();
            $table->string('file_path', 255)->nullable();            // private disk
            $table->string('file_name', 191)->nullable();
            $table->timestamp('submitted_at')->nullable();           // null = teacher recorded marks without a submission
            $table->boolean('is_late')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('status', 10)->default('submitted');      // submitted | evaluated | returned (resubmission wanted)
            $table->decimal('marks', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
            $table->index(['branch_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('learning_materials');
    }
};
