<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance (Part 3B). All dates/times are Bangladesh local time (Asia/Dhaka, DB_TIMEZONE=+06:00).
 *  student_attendances            one row per student per day per slot ("day" or "sub:<subject_id>")
 *  staff_attendances              one row per employee per day (check-in/out, late, early leave, overtime)
 *  attendance_corrections         change requests for past records + approval workflow
 *  attendance_devices             registered biometric / QR terminals (hashed API key)
 *  attendance_punches             raw punches received from devices (idempotent)
 *  attendance_alerts              consecutive-absence alerts (one per student per streak end date)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete(); // subject-wise attendance
            $table->string('slot', 30)->default('day'); // "day" or "sub:<subject_id>" — part of the uniqueness key
            $table->date('date');
            $table->string('status', 12);               // present | absent | late | half_day | leave | excused
            $table->time('check_in_at')->nullable();
            $table->string('method', 12)->default('manual'); // manual | bulk | qr | id_card | biometric | online
            $table->string('remarks', 255)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Master prompt §11: no duplicate attendance records.
            $table->unique(['student_id', 'date', 'slot'], 'student_attendance_unique');
            $table->index(['branch_id', 'date', 'slot', 'status'], 'student_attendance_day_index');
            $table->index(['section_id', 'date', 'slot'], 'student_attendance_section_index');
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('status', 12);               // present | absent | late | half_day | leave | excused
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->string('method', 12)->default('manual'); // manual | qr | id_card | biometric
            $table->foreignId('leave_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('remarks', 255)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date'], 'staff_attendance_unique');
            $table->index(['branch_id', 'date', 'status'], 'staff_attendance_day_index');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('attendable_type', 60);      // morph alias: student_attendance | staff_attendance
            $table->unsignedBigInteger('attendable_id');
            $table->string('current_status', 12);
            $table->string('requested_status', 12);
            $table->string('requested_check_in', 5)->nullable();  // HH:MM (staff only)
            $table->string('requested_check_out', 5)->nullable(); // HH:MM (staff only)
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['attendable_type', 'attendable_id']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('serial_no', 100)->nullable();
            $table->string('type', 20)->default('biometric'); // biometric | qr_terminal | rfid
            $table->string('api_key_hash', 64);               // sha256 of the key; the key itself is shown once
            $table->string('api_key_hint', 8);                // last characters, to recognise the key
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();

            $table->unique('api_key_hash');
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('person_code', 40);           // student_code or employee_code as enrolled on the device
            $table->string('person_type', 10)->nullable(); // student | employee (null = unknown code)
            $table->unsignedBigInteger('person_id')->nullable();
            $table->dateTime('punched_at');
            $table->string('result', 20);                // applied | duplicate | unknown | ignored
            $table->string('message', 255)->nullable();
            $table->timestamps();

            // Idempotency: the same punch re-sent by a device is stored once.
            $table->unique(['branch_id', 'person_code', 'punched_at'], 'attendance_punch_unique');
            $table->index(['branch_id', 'punched_at']);
        });

        Schema::create('attendance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('streak_days');
            $table->date('first_absent_on');
            $table->date('last_absent_on');
            $table->string('status', 20)->default('open'); // open | acknowledged
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('notified_at')->nullable(); // set when the parent notification is queued (Part 5 channels)
            $table->timestamps();

            $table->unique(['student_id', 'first_absent_on'], 'attendance_alert_streak_unique');
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_alerts');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('student_attendances');
    }
};
