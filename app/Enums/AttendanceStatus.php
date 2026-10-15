<?php

namespace App\Enums;

/**
 * Attendance statuses (master prompt §11). Percentage rule used everywhere:
 *   attended = present + late + ½ × half_day
 *   counted  = all marked days − leave − excused
 *   %        = attended ÷ counted × 100
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Leave = 'leave';
    case Excused = 'excused';

    public function label(): string
    {
        return __(match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::HalfDay => 'Half-day',
            self::Leave => 'Leave',
            self::Excused => 'Excused absence',
        });
    }

    /** One-letter code for monthly sheets. */
    public function short(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Absent => 'A',
            self::Late => 'L',
            self::HalfDay => 'H',
            self::Leave => 'LV',
            self::Excused => 'E',
        };
    }

    /** Bootstrap colour name. */
    public function tone(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::HalfDay => 'info',
            self::Leave => 'primary',
            self::Excused => 'secondary',
        };
    }

    /** Weight towards "attended" (present/late = 1, half-day = 0.5). */
    public function attendedWeight(): float
    {
        return match ($this) {
            self::Present, self::Late => 1.0,
            self::HalfDay => 0.5,
            default => 0.0,
        };
    }

    /** Leave and excused absence are excluded from the percentage denominator. */
    public function countsTowardsTotal(): bool
    {
        return ! in_array($this, [self::Leave, self::Excused], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /**
     * Percentage from a status => count map (null when nothing counts, e.g. only leave days).
     *
     * @param  array<string,int|float>  $counts
     */
    public static function percentage(array $counts): ?float
    {
        $attended = 0.0;
        $total = 0.0;
        foreach ($counts as $status => $count) {
            $case = self::tryFrom((string) $status);
            if (! $case || ! $case->countsTowardsTotal()) {
                continue;
            }
            $total += $count;
            $attended += $count * $case->attendedWeight();
        }

        return $total > 0 ? round($attended / $total * 100, 1) : null;
    }
}
