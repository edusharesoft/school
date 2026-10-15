<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\Period;
use App\Models\Academic\Room;
use App\Models\Academic\Section;
use App\Services\Academic\TimetableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Class routine builder, teacher & room timetables, printable routines. */
class TimetableController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected TimetableService $timetables) {}

    public function index(Request $request): View
    {
        $this->authorize('timetables.view');
        $session = $this->session($request);
        $sections = Section::query()->with('schoolClass:id,name,numeric_order')->where('is_active', true)->get()
            ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->values();
        $section = $request->integer('section_id') ? $sections->firstWhere('id', $request->integer('section_id')) : $sections->first();

        return view('branch.academics.timetable.index', [
            'sessions' => AcademicSession::query()->latest('starts_on')->get(['id', 'name', 'is_current']),
            'session' => $session,
            'sections' => $sections,
            'section' => $section,
            'periods' => $this->periodsFor($section?->shift),
            'days' => $this->timetables->days(),
            'grid' => $section && $session ? $this->timetables->grid($section, $session) : collect(),
            'subjects' => $section ? ClassSubject::query()->with('subject:id,name,code')->where('school_class_id', $section->school_class_id)
                ->orderBy('sort_order')->get()->pluck('subject')->filter()->unique('id')->pluck('name', 'id') : collect(),
            'teachers' => $this->timetables->teachers(),
            'rooms' => Room::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'print' => $request->boolean('print'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('timetables.update');

        $data = $request->validate([
            'academic_session_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'cells' => ['nullable', 'array'],
        ]);
        $session = AcademicSession::query()->findOrFail($data['academic_session_id']);
        $section = Section::query()->findOrFail($data['section_id']);

        $result = $this->timetables->save($section, $session, $data['cells'] ?? []);

        return redirect()->route('branch.academics.timetable.index', ['academic_session_id' => $session->id, 'section_id' => $section->id])
            ->with('success', __('Routine saved: :saved changed, :removed cleared.', $result));
    }

    /** Weekly timetable of one teacher (back-office) or of the signed-in teacher (portal). */
    public function teacher(Request $request): View
    {
        $session = $this->session($request);
        $portal = $this->inTeacherPortal($request);
        if (! $portal) {
            $this->authorize('timetables.view');
        }
        $teachers = $portal ? collect([$request->user()->id => $request->user()->name]) : $this->timetables->teachers();
        $teacherId = $portal ? $request->user()->id : ($request->integer('teacher_id') ?: $teachers->keys()->first());

        return view('branch.academics.timetable.teacher', [
            'session' => $session,
            'teachers' => $teachers,
            'teacherId' => $teacherId,
            'periods' => Period::query()->ordered()->get(),
            'days' => $this->timetables->days(),
            'grid' => $session && $teacherId ? $this->timetables->forTeacher($teacherId, $session) : collect(),
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function room(Request $request): View
    {
        $this->authorize('timetables.view');
        $session = $this->session($request);
        $rooms = Room::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id');
        $roomId = $request->integer('room_id') ?: $rooms->keys()->first();

        return view('branch.academics.timetable.room', [
            'session' => $session,
            'rooms' => $rooms,
            'roomId' => $roomId,
            'periods' => Period::query()->ordered()->get(),
            'days' => $this->timetables->days(),
            'grid' => $session && $roomId ? $this->timetables->forRoom($roomId, $session) : collect(),
        ]);
    }

    /** The section's shift periods when that shift has a bell schedule, otherwise all periods. */
    protected function periodsFor(?string $shift)
    {
        $all = Period::query()->ordered()->get();
        $own = $shift ? $all->where('shift', $shift)->values() : collect();

        return $own->isNotEmpty() ? $own : $all;
    }

    protected function session(Request $request): ?AcademicSession
    {
        return $request->integer('academic_session_id')
            ? AcademicSession::query()->find($request->integer('academic_session_id'))
            : AcademicSession::query()->current()->first();
    }
}
