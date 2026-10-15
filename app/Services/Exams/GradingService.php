<?php

namespace App\Services\Exams;

/**
 * Pure grading engine (no database) — covered by tests/Unit/GradingServiceTest.
 *
 * Subject:  every component (theory / practical / CA) with a pass mark must reach it, and the total must reach
 *           the paper's overall pass mark; then the letter grade comes from the percentage band. A failed paper
 *           gets the scale's fail grade and 0 points. Absent → "absent" (fail). Any missing component mark → "incomplete".
 * GPA:      average grade point of the compulsory GPA subjects. Any failed compulsory subject → GPA 0 and fail.
 *           Optional (4th) subject adds only the points above the scale's base point (default 2.00), GPA capped at max.
 *           Any incomplete subject → the whole result is "incomplete" (never published as a GPA).
 *           Rounding: "round" (half up) or "floor" to the scale's decimals.
 */
class GradingService
{
    /**
     * @param  array<int,array{grade:string,min:float|int,max:float|int,point:float|int}>  $grades
     * @return array{grade:string, point:float}
     */
    public function grade(float $percent, array $grades): array
    {
        $bands = $this->bands($grades);
        foreach ($bands as $band) {
            if ($percent >= (float) $band['min'] - 1e-9) {
                return ['grade' => (string) $band['grade'], 'point' => (float) $band['point']];
            }
        }
        $last = end($bands);

        return ['grade' => (string) ($last['grade'] ?? 'F'), 'point' => 0.0];
    }

    /**
     * @param  array<string,array{full:float|int,pass:float|int}>  $components
     * @param  array<string,float|int|null>  $marks  component => marks obtained
     * @return array{status:string, total:?float, percent:?float, grade:?string, point:?float, failed_components:string[]}
     */
    public function subject(array $components, float $passMarks, array $marks, bool $absent, array $grades): array
    {
        $active = array_filter($components, fn ($c) => (float) ($c['full'] ?? 0) > 0);
        $full = array_sum(array_map(fn ($c) => (float) $c['full'], $active));
        $failGrade = $this->failGrade($grades);

        if ($absent) {
            return ['status' => 'absent', 'total' => 0.0, 'percent' => 0.0, 'grade' => $failGrade, 'point' => 0.0, 'failed_components' => array_keys($active)];
        }

        $total = 0.0;
        $failed = [];
        foreach ($active as $name => $component) {
            $value = $marks[$name] ?? null;
            if ($value === null || $value === '') {
                return ['status' => 'incomplete', 'total' => null, 'percent' => null, 'grade' => null, 'point' => null, 'failed_components' => []];
            }
            $value = (float) $value;
            $total += $value;
            if ((float) ($component['pass'] ?? 0) > 0 && $value < (float) $component['pass']) {
                $failed[] = $name;
            }
        }

        $total = round($total, 2);
        $percent = $full > 0 ? round($total / $full * 100, 2) : 0.0;
        $band = $this->grade($percent, $grades);
        $fails = $failed !== [] || $total < $passMarks || $band['point'] <= 0;

        return [
            'status' => $fails ? 'fail' : 'pass',
            'total' => $total,
            'percent' => $percent,
            'grade' => $fails ? $failGrade : $band['grade'],
            'point' => $fails ? 0.0 : $band['point'],
            'failed_components' => $failed,
        ];
    }

    /**
     * @param  array<int,array{status:string, point:?float, is_optional?:bool, counts_in_gpa?:bool}>  $subjects
     * @param  array{grades:array, max_gpa?:float, optional_base_point?:float, gpa_decimals?:int, rounding?:string}  $scale
     * @return array{status:string, gpa:?float, gpa_without_optional:?float, grade:?string, failed_subjects:int}
     */
    public function gpa(array $subjects, array $scale): array
    {
        $counted = array_values(array_filter($subjects, fn ($s) => $s['counts_in_gpa'] ?? true));
        $compulsory = array_values(array_filter($counted, fn ($s) => empty($s['is_optional'])));
        $optional = array_values(array_filter($counted, fn ($s) => ! empty($s['is_optional'])));
        $failGrade = $this->failGrade($scale['grades']);

        if ($compulsory === []) {
            return ['status' => 'incomplete', 'gpa' => null, 'gpa_without_optional' => null, 'grade' => null, 'failed_subjects' => 0];
        }
        foreach ($counted as $s) {
            if ($s['status'] === 'incomplete') {
                return ['status' => 'incomplete', 'gpa' => null, 'gpa_without_optional' => null, 'grade' => null, 'failed_subjects' => 0];
            }
        }

        $failedCompulsory = count(array_filter($compulsory, fn ($s) => in_array($s['status'], ['fail', 'absent'], true)));
        if ($failedCompulsory > 0) {
            return ['status' => 'fail', 'gpa' => 0.0, 'gpa_without_optional' => 0.0, 'grade' => $failGrade, 'failed_subjects' => $failedCompulsory];
        }

        $sum = array_sum(array_map(fn ($s) => (float) $s['point'], $compulsory));
        $n = count($compulsory);
        $base = (float) ($scale['optional_base_point'] ?? 2.0);
        $bonus = 0.0;
        foreach ($optional as $s) {
            if ($s['status'] === 'pass') {
                $bonus += max(0.0, (float) $s['point'] - $base);
            }
        }

        $max = (float) ($scale['max_gpa'] ?? 5.0);
        $decimals = (int) ($scale['gpa_decimals'] ?? 2);
        $rounding = $scale['rounding'] ?? 'round';

        $gpa = $this->roundTo(min($max, ($sum + $bonus) / $n), $decimals, $rounding);
        $without = $this->roundTo(min($max, $sum / $n), $decimals, $rounding);

        return ['status' => 'pass', 'gpa' => $gpa, 'gpa_without_optional' => $without, 'grade' => $this->letterForGpa($gpa, $scale['grades']), 'failed_subjects' => 0];
    }

    /** Letter grade for a GPA: the band with the highest point not above the GPA. */
    public function letterForGpa(float $gpa, array $grades): string
    {
        $byPoint = $grades;
        usort($byPoint, fn ($a, $b) => (float) $b['point'] <=> (float) $a['point']);
        foreach ($byPoint as $band) {
            if ($gpa + 1e-9 >= (float) $band['point']) {
                return (string) $band['grade'];
            }
        }

        return $this->failGrade($grades);
    }

    public function failGrade(array $grades): string
    {
        foreach ($grades as $g) {
            if ((float) $g['point'] <= 0) {
                return (string) $g['grade'];
            }
        }
        usort($grades, fn ($a, $b) => (float) $a['point'] <=> (float) $b['point']);

        return (string) ($grades[0]['grade'] ?? 'F');
    }

    public function roundTo(float $value, int $decimals, string $mode): float
    {
        $factor = 10 ** $decimals;

        return $mode === 'floor' ? floor($value * $factor + 1e-9) / $factor : round($value, $decimals, PHP_ROUND_HALF_UP);
    }

    /** Bands sorted highest minimum first. */
    protected function bands(array $grades): array
    {
        usort($grades, fn ($a, $b) => (float) $b['min'] <=> (float) $a['min']);

        return $grades;
    }
}
