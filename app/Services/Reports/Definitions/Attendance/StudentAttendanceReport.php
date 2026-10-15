<?php

namespace App\Services\Reports\Definitions\Attendance;

use App\Models\Academic\Section;
use App\Services\Attendance\StudentAttendanceService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Month totals of student attendance per student, for one section or for every section. */
class StudentAttendanceReport extends Report
{
    public function __construct(protected StudentAttendanceService $service) {}

    public function key(): string { return 'attendance.students'; }

    public function title(): string { return __('Student attendance (monthly)'); }

    public function description(): string { return __('Present, absent, late, leave and attendance percentage per student for a month.'); }

    public function permission(): string { return 'student-attendance.view'; }

    public function filters(): array
    {
        return [['name' => 'month', 'type' => 'month', 'label' => 'Month', 'default' => fn () => CarbonImmutable::today()->format('Y-m')],
            ['name' => 'section_id', 'type' => 'select', 'label' => 'Section', 'options' => fn () => $this->sections()->mapWithKeys(fn ($s) => [$s->id => trim(($s->schoolClass?->name ?? '').' — '.$s->name)])->all()]];
    }

    public function run(array $filters): R
    {
        $month = CarbonImmutable::parse(($filters['month'] ?? CarbonImmutable::today()->format('Y-m')).'-01');
        $sections = $this->sections()->when($filters['section_id'] ?? null, fn ($c, $id) => $c->where('id', $id));
        $rows = [];

        foreach ($sections as $section) {
            $sheet = $this->service->monthlySheet($section, $month->year, $month->month);
            foreach ($sheet['rows'] as $row) {
                $c = $row['counts'];
                $rows[] = ['class' => $section->schoolClass?->name, 'section' => $section->name, 'roll' => $row['enrollment']->roll_no, 'code' => $row['enrollment']->student?->student_code,
                    'name' => $row['enrollment']->student?->name, 'present' => $c['present'] ?? 0, 'late' => $c['late'] ?? 0, 'half' => $c['half_day'] ?? 0, 'absent' => $c['absent'] ?? 0,
                    'leave' => ($c['leave'] ?? 0) + ($c['excused'] ?? 0), 'percent' => $row['percentage']];
            }
        }

        $result = $this->result([R::col('class', __('Class')), R::col('section', __('Section')), R::col('roll', __('Roll'), 'int'), R::col('code', __('Student ID')), R::col('name', __('Name')),
            R::col('present', __('Present'), 'int', true), R::col('late', __('Late'), 'int', true), R::col('half', __('Half day'), 'int', true), R::col('absent', __('Absent'), 'int', true),
            R::col('leave', __('Leave / excused'), 'int', true), R::col('percent', __('Attendance'), 'percent')], $rows);
        $result->subtitle = $month->format('F Y');

        return $result;
    }

    /** @return \Illuminate\Support\Collection<int,Section> */
    protected function sections()
    {
        return Section::query()->with('schoolClass:id,name,numeric_order')->where('is_active', true)->get()
            ->sortBy(fn (Section $s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->values();
    }
}
