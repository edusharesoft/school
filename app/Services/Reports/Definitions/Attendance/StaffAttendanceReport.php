<?php

namespace App\Services\Reports\Definitions\Attendance;

use App\Services\Attendance\StaffAttendanceService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Month totals of staff attendance with late and overtime minutes. */
class StaffAttendanceReport extends Report
{
    public function __construct(protected StaffAttendanceService $service) {}

    public function key(): string { return 'attendance.staff'; }

    public function title(): string { return __('Staff attendance (monthly)'); }

    public function description(): string { return __('Present, absent, late and leave days per employee for a month, with late and overtime minutes.'); }

    public function permission(): string { return 'staff-attendance.view'; }

    public function filters(): array
    {
        return [['name' => 'month', 'type' => 'month', 'label' => 'Month', 'default' => fn () => CarbonImmutable::today()->format('Y-m')],
            ['name' => 'type', 'type' => 'select', 'label' => 'Type', 'options' => ['teacher' => __('Teachers'), 'staff' => __('Staff')]]];
    }

    public function run(array $filters): R
    {
        $month = CarbonImmutable::parse(($filters['month'] ?? CarbonImmutable::today()->format('Y-m')).'-01');
        $sheet = $this->service->monthlySheet($month->year, $month->month, $filters['type'] ?? null);

        $rows = $sheet['rows']->map(function ($row) {
            $c = $row['counts'];

            return ['code' => $row['employee']->employee_code, 'name' => $row['employee']->name, 'designation' => $row['employee']->designation?->name, 'present' => $c['present'] ?? 0, 'late' => $c['late'] ?? 0,
                'half' => $c['half_day'] ?? 0, 'absent' => $c['absent'] ?? 0, 'leave' => ($c['leave'] ?? 0) + ($c['excused'] ?? 0), 'late_min' => $row['late_minutes'], 'ot_min' => $row['overtime_minutes'], 'percent' => $row['percentage']];
        })->all();

        $result = $this->result([R::col('code', __('Employee ID')), R::col('name', __('Name')), R::col('designation', __('Designation')), R::col('present', __('Present'), 'int', true), R::col('late', __('Late'), 'int', true),
            R::col('half', __('Half day'), 'int', true), R::col('absent', __('Absent'), 'int', true), R::col('leave', __('Leave / excused'), 'int', true), R::col('late_min', __('Late minutes'), 'int', true),
            R::col('ot_min', __('Overtime minutes'), 'int', true), R::col('percent', __('Attendance'), 'percent')], $rows);
        $result->subtitle = $month->format('F Y');

        return $result;
    }
}
