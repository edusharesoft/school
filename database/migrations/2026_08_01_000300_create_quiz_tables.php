<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learning management, part 3 (Part 5-3c): online quizzes with automatic grading.
 *  quizzes          class / section quiz: time limit, attempts, availability window, answer-review policy
 *  quiz_questions   multiple choice (single / multiple answers) and true-false questions with marks
 *  quiz_attempts    one row per attempt of a student: saved answers, score, timing
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 191);
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();   // null = untimed
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->string('show_answers', 12)->default('after_close');       // never | after_submit | after_close
            $table->decimal('pass_percent', 5, 2)->nullable();
            $table->string('status', 10)->default('draft');                   // draft | published | closed
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'school_class_id', 'section_id']);
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('type', 12);                                       // mcq_single | mcq_multi | true_false
            $table->text('text');
            $table->json('options');                                          // ["…", "…"]
            $table->json('correct');                                          // indexes into options
            $table->decimal('marks', 6, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'position']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->json('question_order');                                   // question ids in the order shown
            $table->json('answers')->nullable();                              // question id => [option indexes]
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('max_score', 7, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->boolean('timed_out')->default(false);
            $table->timestamps();

            $table->unique(['quiz_id', 'student_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
