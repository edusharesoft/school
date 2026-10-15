<?php

namespace App\Http\Controllers\Branch\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CorrectionRequest;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\StaffAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Services\Attendance\AttendanceCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Attendance correction requests and their approval (master prompt §11). */
class AttendanceCorrectionController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected AttendanceCorrectionService $service) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $approveStudents = $user->can('student-attendance.approve-correction');
        $approveStaff = $user->can('staff-attendance.approve-correction');
        abort_unless($approveStudents || $approveStaff || $user->can('student-attendance.correct') || $user->can('staff-attendance.correct'), 403);

        $status = $request->string('status')->toString() ?: 'pending';

        $corrections = AttendanceCorrection::query()
            ->with(['attendable', 'requester:id,name', 'reviewer:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->where(function ($q) use ($user, $approveStudents, $approveStaff) {
                $q->where('requested_by', $user->id);
                if ($approveStudents) {
                    $q->orWhere('attendable_type', 'student_attendance');
                }
                if ($approveStaff) {
                    $q->orWhere('attendable_type', 'staff_attendance');
                }
            })
            ->latest()
            ->paginate(25)->withQueryString();

        $corrections->getCollection()->each(function (AttendanceCorrection $c) {
            $c->attendable?->loadMissing($c->attendable instanceof StaffAttendance ? 'employee:id,name,employee_code,user_id' : ['student:id,name,student_code', 'section.schoolClass:id,name']);
        });

        return view('branch.attendance.corrections', [
            'corrections' => $corrections,
            'status' => $status,
            'approveStudents' => $approveStudents,
            'approveStaff' => $approveStaff,
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(CorrectionRequest $request): RedirectResponse
    {
        $user = $request->user();

        $record = $request->validated('type') === 'staff'
            ? StaffAttendance::query()->findOrFail($request->validated('record_id'))
            : StudentAttendance::query()->with('section')->findOrFail($request->validated('record_id'));

        // Teachers may request corrections only for sections they are class teacher of.
        if ($record instanceof StudentAttendance && $user->portal() === 'teacher') {
            abort_unless($record->section?->class_teacher_id === $user->id, 403);
        }

        $correction = $this->service->request(
            $record,
            AttendanceStatus::from($request->validated('requested_status')),
            $request->validated('reason'),
            $user,
            $request->validated('requested_check_in'),
            $request->validated('requested_check_out'),
        );

        return back()->with('success', $correction->status === 'approved'
            ? __('Correction applied.')
            : __('Correction request submitted for approval.'));
    }

    public function approve(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->service->approve($correction, $request->user(), $data['note'] ?? null);

        return back()->with('success', __('Correction approved and applied.'));
    }

    public function reject(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $this->service->reject($correction, $request->user(), $data['note']);

        return back()->with('success', __('Correction request rejected.'));
    }
}
