<?php

namespace App\Http\Controllers\Branch\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StaffAttendanceRequest;
use App\Models\Department;
use App\Services\Attendance\AttendanceSettings;
use App\Services\Attendance\StaffAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Daily teacher & employee register (manual marking, times, approved-leave aware). */
class StaffAttendanceController extends Controller
{
    public function __construct(protected StaffAttendanceService $service, protected AttendanceSettings $settings) {}

    public function index(Request $request): View
    {
        $this->authorize('staff-attendance.view');

        $date = $request->query('date') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date'))
            ? CarbonImmutable::parse($request->query('date'))->startOfDay() : CarbonImmutable::today();
        $type = in_array($request->query('type'), ['teacher', 'staff'], true) ? $request->query('type') : null;

        $rows = $this->service->register($date, $type, $request->integer('department_id') ?: null);

        try {
            $this->service->assertMarkable($date);
            $markableReason = null;
        } catch (ValidationException $e) {
            $markableReason = collect($e->errors())->flatten()->first();
        }

        $counts = $rows->filter(fn ($r) => $r['record'])->countBy(fn ($r) => $r['record']->status->value)->all();

        return view('branch.attendance.staff.index', [
            'date' => $date,
            'type' => $type,
            'rows' => $rows,
            'counts' => $counts,
            'markableReason' => $markableReason,
            'canMark' => $request->user()->can('staff-attendance.mark') && $markableReason === null,
            'canCorrect' => $request->user()->can('staff-attendance.correct'),
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => AttendanceStatus::cases(),
            'settings' => $this->settings->all(),
        ]);
    }

    public function store(StaffAttendanceRequest $request): RedirectResponse
    {
        $date = CarbonImmutable::parse($request->validated('date'))->startOfDay();
        $result = $this->service->saveDay($date, $request->validated('entries'), $request->user());

        $message = __('Register saved: :created new, :updated changed, :unchanged unchanged.', $result);
        if ($result['skipped']) {
            $message .= ' '.__(':count row(s) skipped (approved leave or past records — use a correction request).', ['count' => $result['skipped']]);
        }

        return redirect()->route('branch.attendance.staff.index', ['date' => $date->toDateString()] + $request->only('type', 'department_id'))
            ->with('success', $message);
    }
}
