<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StudentAttendanceRequest;
use App\Models\Academic\Section;
use App\Services\Attendance\StudentAttendanceService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Student attendance for mobile apps:
 *   GET  /api/v1/attendance/students?section_id=&date=   register for a section/date
 *   POST /api/v1/attendance/students                     save a register (same rules as the web)
 */
class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(protected StudentAttendanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        $section = Section::query()->findOrFail($data['section_id']);
        abort_unless(Gate::allows('attendance.mark-section', $section)
            || ($request->user()->can('student-attendance.view') && $request->user()->portal() !== 'teacher'), 403);

        $date = isset($data['date']) ? CarbonImmutable::parse($data['date']) : CarbonImmutable::today();
        $rows = $this->service->register($section, $date, $data['subject_id'] ?? null);

        return $this->ok($rows->map(fn ($r) => [
            'student_id' => $r['enrollment']->student_id,
            'student_code' => $r['enrollment']->student?->student_code,
            'name' => $r['enrollment']->student?->name,
            'roll_no' => $r['enrollment']->roll_no,
            'status' => $r['record']?->status->value,
            'remarks' => $r['record']?->remarks,
        ])->values(), null, 200, [
            'date' => $date->toDateString(),
            'section_id' => $section->id,
            'statuses' => AttendanceStatus::values(),
        ]);
    }

    public function store(StudentAttendanceRequest $request): JsonResponse
    {
        $section = Section::query()->findOrFail($request->validated('section_id'));
        Gate::authorize('attendance.mark-section', $section);

        $result = $this->service->save($section, CarbonImmutable::parse($request->validated('date')), $request->validated('entries'),
            $request->user(), $request->validated('subject_id'), 'online');

        return $this->ok($result, __('Register saved.'));
    }
}
