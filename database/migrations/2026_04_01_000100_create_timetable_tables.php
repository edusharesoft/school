<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class routine (Part 3C).
 *  periods            the branch's bell schedule (per shift), including breaks
 *  timetable_entries  section × day × period → subject, teacher, room (per academic session)
 * Teacher and room double-booking are checked by TimetableService; the unique key stops two
 * entries for the same section slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 40);                 // "1st period", "Tiffin break"
            $table->string('shift', 20)->default('day'); // morning | day | evening
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_break')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'shift', 'sort_order']);
        });

        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');  // ISO 1 (Mon) … 7 (Sun)
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 100)->nullable();
            $table->timestamps();

            $table->unique(['academic_session_id', 'section_id', 'day_of_week', 'period_id'], 'timetable_section_slot_unique');
            $table->index(['academic_session_id', 'teacher_id', 'day_of_week', 'period_id'], 'timetable_teacher_slot_index');
            $table->index(['academic_session_id', 'room_id', 'day_of_week', 'period_id'], 'timetable_room_slot_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('periods');
    }
};
