<?php

namespace App\Services\Reports\Definitions\Hostel;

use App\Models\Hostel\Attendance;
use App\Models\Hostel\Hostel;
use App\Models\Students\Student;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Illuminate\Support\Facades\DB;

/** Hostel roll-call totals per resident for a period. */
class HostelAttendanceReport extends Report
{
    public function key(): string { return 'hostel.attendance'; }

    public function title(): string { return __('Hostel roll call'); }

    public function description(): string { return __('Present, absent, leave and late counts per resident in a period.'); }

    public function permission(): string { return 'hostel.view'; }

    public function filters(): array
    {
        return [...$this->periodFilters(), ['name' => 'hostel', 'type' => 'select', 'label' => 'Hostel', 'options' => fn () => Hostel::query()->orderBy('name')->pluck('name', 'id')->all()]];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $counts = Attendance::query()->toBase()->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->when($filters['hostel'] ?? null, fn ($q, $v) => $q->where('hostel_id', $v))
            ->select('student_id', 'hostel_id', 'status', DB::raw('COUNT(*) as n'))->groupBy('student_id', 'hostel_id', 'status')->get();
        $students = Student::query()->whereIn('id', $counts->pluck('student_id')->unique())->get(['id', 'name', 'student_code'])->keyBy('id');
        $hostels = Hostel::query()->pluck('name', 'id');

        $rows = [];
        foreach ($counts->groupBy(fn ($c) => $c->student_id.'-'.$c->hostel_id) as $group) {
            $by = $group->pluck('n', 'status');
            $present = (int) ($by['present'] ?? 0);
            $late = (int) ($by['late'] ?? 0);
            $absent = (int) ($by['absent'] ?? 0);
            $leave = (int) ($by['leave'] ?? 0);
            $days = $present + $late + $absent + $leave;
            $s = $students->get($group->first()->student_id);
            $rows[] = ['hostel' => $hostels[$group->first()->hostel_id] ?? '', 'code' => $s?->student_code, 'student' => $s?->name, 'present' => $present, 'late' => $late, 'absent' => $absent, 'leave' => $leave,
                'days' => $days, 'rate' => $days ? round(($present + $late) / $days * 100, 1) : 0];
        }
        usort($rows, fn ($a, $b) => [$a['hostel'], $a['student']] <=> [$b['hostel'], $b['student']]);

        return $this->result([R::col('hostel', __('Hostel')), R::col('code', __('Student ID')), R::col('student', __('Student')), R::col('present', __('Present'), 'int', true), R::col('late', __('Late'), 'int', true),
            R::col('absent', __('Absent'), 'int', true), R::col('leave', __('Leave'), 'int', true), R::col('days', __('Days marked'), 'int', true), R::col('rate', __('Attendance'), 'percent')], $rows);
    }
}
