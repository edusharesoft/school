<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Events\Attendance\ConsecutiveAbsenceDetected;
use App\Models\Academic\AcademicSession;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\StudentAttendance;
use App\Models\Students\StudentEnrollment;
use Carbon\CarbonImmutable;

/**
 * Finds students absent on N consecutive register days of their section (N = attendance.absence_alert_days).
 * "Register days" are the dates on which the section's daily register was actually taken, so weekly
 * holidays and closures never break or extend a streak. Idempotent: one alert per streak, updated as it grows.
 */
class AbsenceAlertService
{
    /** How many recent register days are examined per section. */
    public const LOOKBACK_DAYS = 30;

    public function __construct(protected AttendanceSettings $settings) {}

    /** @return array{sections:int, created:int, updated:int} */
    public function detect(?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::today();
        $threshold = max(2, (int) $this->settings->get('absence_alert_days'));
        $session = AcademicSession::query()->current()->first();
        $stats = ['sections' => 0, 'created' => 0, 'updated' => 0];

        if (! $session) {
            return $stats;
        }

        $sectionIds = StudentEnrollment::query()->where('academic_session_id', $session->id)
            ->where('status', 'enrolled')->distinct()->pluck('section_id');

        foreach ($sectionIds as $sectionId) {
            $dates = StudentAttendance::query()->where('section_id', $sectionId)->where('slot', 'day')
                ->whereDate('date', '<=', $asOf->toDateString())
                ->select('date')->distinct()->orderByDesc('date')->limit(self::LOOKBACK_DAYS)
                ->pluck('date')->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->unique()->values();

            if ($dates->count() < $threshold) {
                continue;
            }
            $stats['sections']++;

            $records = StudentAttendance::query()->where('section_id', $sectionId)->where('slot', 'day')
                ->whereDate('date', '>=', $dates->last())->whereDate('date', '<=', $dates->first())
                ->get(['student_id', 'date', 'status']);

            $byStudent = [];
            foreach ($records as $r) {
                $byStudent[$r->student_id][$r->date->toDateString()] = $r->status;
            }

            foreach ($byStudent as $studentId => $marks) {
                $streak = [];
                foreach ($dates as $date) {
                    if (($marks[$date] ?? null) !== AttendanceStatus::Absent) {
                        break;
                    }
                    $streak[] = $date;
                }

                if (count($streak) < $threshold) {
                    continue;
                }

                // whereDate: DATE columns compare reliably on every driver (SQLite stores a time part).
                $alert = AttendanceAlert::query()->where('student_id', $studentId)->whereDate('first_absent_on', end($streak))->first()
                    ?? new AttendanceAlert(['student_id' => $studentId, 'first_absent_on' => end($streak)]);
                $isNew = ! $alert->exists;
                $alert->fill([
                    'section_id' => $sectionId,
                    'streak_days' => count($streak),
                    'last_absent_on' => $streak[0],
                    'status' => $alert->status ?? 'open',
                ]);

                if ($isNew || $alert->isDirty('streak_days')) {
                    $alert->save();
                    $isNew ? $stats['created']++ : $stats['updated']++;
                    if ($isNew) {
                        ConsecutiveAbsenceDetected::dispatch($alert);
                    }
                }
            }
        }

        return $stats;
    }
}
