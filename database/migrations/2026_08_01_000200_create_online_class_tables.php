<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learning management, part 2 (Part 5-3b):
 *  online_class_integrations   per-branch Zoom / Google Meet credentials and OAuth tokens (encrypted at rest)
 *  online_classes              scheduled live classes: online (Zoom, Google Meet or a manual link) or offline (room)
 *  online_class_attendances    attendance of students in a class session
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_class_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('provider', 20);                       // zoom | google_meet
            $table->boolean('is_enabled')->default(false);
            $table->text('config')->nullable();                   // encrypted JSON: client id / secret, account id, host, redirect uri
            $table->text('tokens')->nullable();                   // encrypted JSON: access / refresh token + expiry
            $table->string('account_label', 191)->nullable();     // e.g. the connected Google account e-mail
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'provider']);
        });

        Schema::create('online_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('mode', 8)->default('online');          // online | offline
            $table->string('provider', 20)->nullable();            // zoom | google_meet | manual (online only)
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('room', 120)->nullable();               // offline classes
            $table->string('join_url', 500)->nullable();
            $table->string('host_url', 1000)->nullable();
            $table->string('meeting_id', 120)->nullable();
            $table->string('passcode', 120)->nullable();
            $table->string('external_id', 191)->nullable();        // Zoom meeting id / Google event id
            $table->string('recording_url', 500)->nullable();
            $table->string('status', 10)->default('scheduled');    // scheduled | completed | cancelled
            $table->string('sync_status', 8)->default('none');     // none | synced | failed
            $table->string('sync_error', 500)->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
            $table->index(['branch_id', 'school_class_id', 'section_id']);
        });

        Schema::create('online_class_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('online_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 8);                           // present | late | absent | excused
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['online_class_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_class_attendances');
        Schema::dropIfExists('online_classes');
        Schema::dropIfExists('online_class_integrations');
    }
};
