<?php

namespace App\Http\Controllers\Branch\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Academic\Section;
use App\Models\Attendance\AttendanceAlert;
use App\Services\Attendance\StaffAttendanceService;
use App\Services\Attendance\StudentAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Monthly attendance sheets (screen, print, CSV) and consecutive-absence alerts. */
class AttendanceReportController extends Controller
{
    public function students(Request $request, StudentAttendanceService $service): View|StreamedResponse
    {
        $this->authorize('student-attendance.view');

        $month = $this->month($request);
        $sections = Section::query()->with('schoolClass:id,name,numeric_order')->where('is_active', true)->get()
            ->sortBy(fn (Section $s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->values();
        $section = $request->integer('section_id') ? $sections->firstWhere('id', $request->integer('section_id')) : null;
        abort_if($request->integer('section_id') && ! $section, 404);

        $sheet = $section ? $service->monthlySheet($section, $month->year, $month->month) : null;

        if ($sheet && $request->query('export') === 'csv') {
            return $this->csv('student-attendance-'.$section->schoolClass?->name.'-'.$section->name.'-'.$month->format('Y-m').'.csv', $sheet,
                ['Roll', 'Student ID', 'Name'],
                fn ($row) => [$row['enrollment']->roll_no, $row['enrollment']->student?->student_code, $row['enrollment']->student?->name]);
        }

        return view('branch.attendance.reports.students', compact('month', 'sections', 'section', 'sheet'));
    }

    public function staff(Request $request, StaffAttendanceService $service): View|StreamedResponse
    {
        $this->authorize('staff-attendance.view');

        $month = $this->month($request);
        $type = in_array($request->query('type'), ['teacher', 'staff'], true) ? $request->query('type') : null;
        $sheet = $service->monthlySheet($month->year, $month->month, $type);

        if ($request->query('export') === 'csv') {
            return $this->csv('staff-attendance-'.$month->format('Y-m').'.csv', $sheet,
                ['Employee ID', 'Name', 'Designation'],
                fn ($row) => [$row['employee']->employee_code, $row['employee']->name, $row['employee']->designation?->name],
                fn ($row) => [$row['late_minutes'], $row['overtime_minutes']], ['Late minutes', 'Overtime minutes']);
        }

        return view('branch.attendance.reports.staff', compact('month', 'type', 'sheet'));
    }

    public function alerts(Request $request): View
    {
        $this->authorize('student-attendance.view');

        $alerts = AttendanceAlert::query()->with(['student:id,name,student_code,phone', 'student.guardians', 'section.schoolClass:id,name', 'acknowledger:id,name'])
            ->when($request->query('status') !== 'all', fn ($q) => $q->where('status', 'open'))
            ->latest('last_absent_on')->paginate(25)->withQueryString();

        return view('branch.attendance.reports.alerts', compact('alerts'));
    }

    public function acknowledge(Request $request, AttendanceAlert $alert): RedirectResponse
    {
        abort_unless($request->user()->can('student-attendance.correct') || $request->user()->can('student-attendance.approve-correction'), 403);

        $alert->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);

        return back()->with('success', __('Alert marked as followed up.'));
    }

    protected function month(Request $request): CarbonImmutable
    {
        $value = (string) $request->query('month');

        return preg_match('/^\d{4}-\d{2}$/', $value) ? CarbonImmutable::createFromFormat('!Y-m', $value) : CarbonImmutable::today()->startOfMonth();
    }

    /** CSV month grid: fixed columns, one column per day (status code), totals and percentage. */
    protected function csv(string $filename, array $sheet, array $head, callable $fixed, ?callable $extra = null, array $extraHead = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheet, $head, $fixed, $extra, $extraHead) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Bangla correctly
            fputcsv($out, array_merge($head, array_map(fn ($d) => $d->format('d'), $sheet['days']), ['P', 'A', 'L', 'H', 'LV', 'E', '%'], $extraHead));

            foreach ($sheet['rows'] as $row) {
                $days = [];
                foreach ($sheet['days'] as $day) {
                    $days[] = isset($row['marks'][$day->toDateString()]) ? $row['marks'][$day->toDateString()]->short() : ($sheet['holidays'][$day->toDateString()] ? '-' : '');
                }
                $c = $row['counts'];
                fputcsv($out, array_merge($fixed($row), $days, [
                    $c['present'], $c['absent'], $c['late'], $c['half_day'], $c['leave'], $c['excused'], $row['percentage'] ?? '',
                ], $extra ? $extra($row) : []));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
