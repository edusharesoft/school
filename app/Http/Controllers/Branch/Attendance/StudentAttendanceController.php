<?php

namespace App\Http\Controllers\Branch\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StudentAttendanceRequest;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\Section;
use App\Models\Attendance\StudentAttendance;
use App\Services\Attendance\AttendanceSettings;
use App\Services\Attendance\StudentAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Class registers (daily or subject-wise). Back-office users with the permission see every section;
 * teachers (teacher portal) see only the sections they are class teacher of.
 */
class StudentAttendanceController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected StudentAttendanceService $service, protected AttendanceSettings $settings) {}

    /** Overview of every section for a date: taken / not taken, present / absent counts. */
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('student-attendance.view') || $request->user()->can('student-attendance.mark'), 403);

        $date = $this->date($request);
        $session = $this->service->currentSession();
        $user = $request->user();

        $sections = Section::query()->with(['schoolClass:id,name,numeric_order', 'classTeacher:id,name'])
            ->where('is_active', true)
            ->when($this->inTeacherPortal($request), fn ($q) => $q->where('class_teacher_id', $user->id))
            ->get()
            ->sortBy(fn (Section $s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))
            ->values();

        $enrolled = $session ? DB::table('student_enrollments')->where('academic_session_id', $session->id)->where('status', 'enrolled')
            ->whereIn('section_id', $sections->pluck('id'))->select('section_id', DB::raw('COUNT(*) as total'))
            ->groupBy('section_id')->pluck('total', 'section_id') : collect();

        $counts = StudentAttendance::query()->whereIn('section_id', $sections->pluck('id'))
            ->whereDate('date', $date->toDateString())->where('slot', 'day')
            ->select('section_id', 'status', DB::raw('COUNT(*) as total'))->groupBy('section_id', 'status')->toBase()->get()
            ->groupBy('section_id')->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($v) => (int) $v)->all());

        return view('branch.attendance.students.index', [
            'date' => $date,
            'session' => $session,
            'sections' => $sections,
            'enrolled' => $enrolled,
            'counts' => $counts,
            'isHoliday' => $this->settings->isWeeklyHoliday($date),
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    /** Register form for one section on one date (optionally a subject period). */
    public function register(Request $request, Section $section): View
    {
        $date = $this->date($request);
        $subjectId = $request->integer('subject_id') ?: null;
        $canMark = Gate::allows('attendance.mark-section', $section);

        abort_unless($canMark || ($request->user()->can('student-attendance.view') && ! $this->inTeacherPortal($request)), 403);

        $section->load('schoolClass:id,name');
        $rows = $this->service->register($section, $date, $subjectId);
        $taken = $rows->contains(fn ($r) => $r['record'] !== null);
        $editable = $canMark && ! ($taken && ! $date->isToday());

        try {
            $this->service->assertMarkable($date);
            $markableReason = null;
        } catch (ValidationException $e) {
            $markableReason = collect($e->errors())->flatten()->first();
            $editable = false;
        }

        return view('branch.attendance.students.register', [
            'section' => $section,
            'date' => $date,
            'rows' => $rows,
            'taken' => $taken,
            'editable' => $editable,
            'markableReason' => $markableReason,
            'subjectId' => $subjectId,
            'subjects' => ClassSubject::query()->with('subject:id,name,code')->where('school_class_id', $section->school_class_id)
                ->orderBy('sort_order')->get()->pluck('subject')->filter()->unique('id')->values(),
            'statuses' => AttendanceStatus::cases(),
            'canCorrect' => $request->user()->can('student-attendance.correct'),
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(StudentAttendanceRequest $request): RedirectResponse
    {
        /** @var Section $section */
        $section = Section::query()->findOrFail($request->validated('section_id'));
        Gate::authorize('attendance.mark-section', $section);

        $date = CarbonImmutable::parse($request->validated('date'))->startOfDay();
        $subjectId = $request->validated('subject_id');

        $result = $this->service->save($section, $date, $request->validated('entries'), $request->user(), $subjectId,
            $request->input('method') === 'bulk' ? 'bulk' : 'manual');

        return redirect()->route($this->routePrefix($request).'attendance.students.register', ['section' => $section, 'date' => $date->toDateString(), 'subject_id' => $subjectId])
            ->with('success', __('Register saved: :created new, :updated changed, :unchanged unchanged.', $result));
    }

    protected function date(Request $request): CarbonImmutable
    {
        $value = $request->query('date');

        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)
            ? CarbonImmutable::parse($value)->startOfDay()
            : CarbonImmutable::today();
    }
}
