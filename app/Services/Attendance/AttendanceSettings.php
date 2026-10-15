<?php

namespace App\Services\Attendance;

use App\Services\Settings\SettingsService;

/**
 * Attendance rules, stored per branch in system_settings (group "attendance") with institution /
 * platform fallback. All times are Bangladesh local time (HH:MM, Asia/Dhaka).
 */
class AttendanceSettings
{
    public const GROUP = 'attendance';

    public const DEFAULTS = [
        'student_start' => '08:00',       // class start; scans after start + grace are "late"
        'student_grace_minutes' => 10,
        'staff_start' => '07:45',         // office start
        'staff_end' => '14:30',           // office end (early leave before, overtime after)
        'staff_grace_minutes' => 10,      // late only after start + grace
        'early_leave_grace_minutes' => 10,
        'overtime_min_minutes' => 30,     // overtime counted only above this threshold
        'half_day_minutes' => 240,        // present for less than this = half-day
        'backdate_days' => 3,             // days back a register may still be taken if none exists
        'absence_alert_days' => 3,        // consecutive absent school days that raise an alert
        'weekly_holidays' => [5],         // ISO weekday numbers (5 = Friday); Saturday = 6
    ];

    public function __construct(protected SettingsService $settings) {}

    public function get(string $key): mixed
    {
        return $this->settings->get(self::GROUP, $key, self::DEFAULTS[$key] ?? null);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function save(array $values): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $values)) {
                $this->settings->set(self::GROUP, $key, $values[$key], 'branch');
            }
        }
    }

    public function isWeeklyHoliday(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('N'), array_map('intval', (array) $this->get('weekly_holidays')), true);
    }
}
