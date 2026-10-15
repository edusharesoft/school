<?php

namespace App\Http\Controllers\Branch\Exams;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Models\Academic\Section;
use App\Models\Exams\ExamMark;
use App\Models\Exams\Examination;
use App\Models\Exams\ExaminationSubject;
use App\Services\Exams\MarkEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Marks entry: papers & sections of an exam, and the per-section marks sheet. Shared with the teacher portal. */
class MarkController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected MarkEntryService $marks) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('marks.enter') || $user->can('marks.view'), 403);

        $exams = Examination::query()->whereIn('status', ['scheduled', 'marks_entry', 'processing', 'approved', 'published'])
            ->latest('starts_on')->latest('id')->limit(30)->get(['id', 'name', 'status', 'academic_session_id', 'starts_on']);
        $exam = $request->integer('exam_id') ? $exams->firstWhere('id', $request->integer('exam_id'))
            : ($exams->first(fn ($e) => $e->acceptsMarks()) ?? $exams->first());

        $rows = collect();
        if ($exam) {
            $unrestricted = $user->can('examinations.update');
            $pairs = $unrestricted ? null : $this->marks->teachingPairs($user, $exam);
            $progress = $this->marks->progress($exam);
            $papers = $exam->subjects()->with(['subject:id,name,code', 'schoolClass:id,name,numeric_order', 'studyGroup:id,name'])->get();
            $sections = Section::query()->whereIn('school_class_id', $papers->pluck('school_class_id')->unique())->where('is_active', true)
                ->orderBy('name')->get()->groupBy('school_class_id');

            foreach ($papers->sortBy(fn ($p) => sprintf('%05d-%05d', $p->schoolClass?->numeric_order ?? 0, $p->sort_order)) as $paper) {
                foreach ($sections->get($paper->school_class_id, collect()) as $section) {
                    $mine = $unrestricted || $pairs->contains($section->id.'-'.$paper->subject_id);
                    if (! $mine && ($this->inTeacherPortal($request) || ! $user->can('marks.view'))) {
                        continue;
                    }
                    $rows->push(['paper' => $paper, 'section' => $section, 'mine' => $mine && $user->can('marks.enter'),
                        'progress' => $progress[$paper->id.'-'.$section->id] ?? ['entered' => 0, 'expected' => 0]]);
                }
            }
        }

        return view('branch.exams.marks.index', [
            'exams' => $exams, 'exam' => $exam, 'rows' => $rows,
            'layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function sheet(Request $request, Examination $exam, ExaminationSubject $paper, Section $section): View
    {
        $this->guard($exam, $paper, $section);
        $user = $request->user();
        $canEnter = $this->marks->canEnter($user, $paper, $section);
        abort_unless($canEnter || (! $this->inTeacherPortal($request) && $user->can('marks.view')), 403);

        $paper->load(['subject:id,name,code', 'schoolClass:id,name', 'studyGroup:id,name']);

        return view('branch.exams.marks.sheet', [
            'exam' => $exam, 'paper' => $paper, 'section' => $section,
            'components' => $paper->activeComponents(),
            'candidates' => $this->marks->candidates($paper, $section),
            'marks' => ExamMark::query()->where('examination_subject_id', $paper->id)->with(['corrections' => fn ($q) => $q->where('status', 'pending')])
                ->get()->keyBy('student_id'),
            'editable' => $canEnter && $exam->acceptsMarks(),
            'correctable' => $canEnter && $exam->needsCorrectionRequest(),
            'layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(Request $request, Examination $exam, ExaminationSubject $paper, Section $section): RedirectResponse
    {
        $this->guard($exam, $paper, $section);
        abort_unless($this->marks->canEnter($request->user(), $paper, $section), 403);
        $data = $request->validate(['entries' => ['required', 'array']]);

        $result = $this->marks->save($paper, $section, $data['entries'], $request->user());

        return back()->with('success', __('Marks saved: :saved changed, :cleared cleared.', $result));
    }

    protected function guard(Examination $exam, ExaminationSubject $paper, Section $section): void
    {
        abort_unless($paper->examination_id === $exam->id && (int) $section->school_class_id === (int) $paper->school_class_id, 404);
        $paper->setRelation('examination', $exam);
    }
}
