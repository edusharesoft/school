<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 7 — indexes found by `php artisan performance:audit` on columns that screens filter by but that were in no index.
 * Each one is created only when the table / column exists and nothing already covers it, so the migration is safe to re-run.
 */
return new class extends Migration
{
    /** table => columns */
    private array $indexes = [
        'attendance_punches' => ['person_id'],
        'notification_messages' => ['recipient_id'],
        'online_classes' => ['meeting_id', 'external_id', 'status'],
        'online_class_attendances' => ['status'],
        'student_enrollments' => ['status'],
        'exam_results' => ['status'],
        'journal_entries' => ['status'],
        'payroll_runs' => ['status'],
        'supplier_payments' => ['status'],
        'goods_receipts' => ['status'],
        'stock_issues' => ['status'],
        'stock_adjustments' => ['status'],
        'assignment_submissions' => ['status'],
        'quizzes' => ['status'],
        'hostel_attendance' => ['status'],
        'academic_sessions' => ['status'],
        'transport_vehicles' => ['status'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column) || Schema::hasIndex($table, [$column])) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($column, "idx_{$table}_{$column}"));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasIndex($table, "idx_{$table}_{$column}")) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex("idx_{$table}_{$column}"));
                }
            }
        }
    }
};
