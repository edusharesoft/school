<?php

namespace App\Http\Controllers\Branch\Exams;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Term;
use App\Models\Exams\Examination;
use App\Models\Exams\ExaminationSubject;
use App\Models\Exams\GradingScale;
use App\Services\Exams\ExaminationService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Exams: list, create / edit, classes & subject papers, schedule, printable exam routine, status. */
class ExaminationController extends Controller
{
    public function __construct(protected ExaminationService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('examinations.view');

        $exams = Examination::query()->with(['session:id,name', 'term:id,name'])->withCount('subjects')
            ->when($request->integer('academic_session_id'), fn ($q, $id) => $q->where('academic_session_id', $id))
            ->latest('starts_on')->latest('id')->paginate(20)->withQueryString();

        return view('branch.exams.index', ['exams' => $exams, 'sessions' => AcademicSession::query()->latest('starts_on')->pluck('name', 'id')]);
    }

    public function create(): View
    {
        $this->authorize('examinations.create');

        return view('branch.exams.form', $this->options() + ['exam' => new Examination([
            'type' => 'term', 'status' => 'draft', 'grading_scale_id' => GradingScale::defaultForBranch()->id,
            'academic_session_id' => AcademicSession::query()->current()->value('id'),
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('examinations.create');
        $exam = Examination::query()->create($this->validated($request) + ['status' => 'draft']);

        return redirect()->route('branch.exams.show', $exam)->with('success', __('Exam created. Now add the classes that sit it.'));
    }

    public function edit(Examination $exam): View
    {
        $this->authorize('examinations.update');

        return view('branch.exams.form', $this->options() + ['exam' => $exam]);
    }

    public function update(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('examinations.update');
        abort_if(in_array($exam->status, ['approved', 'published'], true), 422, __('An approved or published exam cannot be edited.'));
        $exam->update($this->validated($request, $exam));

        return redirect()->route('branch.exams.show', $exam)->with('success', __('Exam updated.'));
    }

    /** Classes & papers of the exam: components, full / pass marks, schedule. */
    public function show(Request $request, Examination $exam): View
    {
        $this->authorize('examinations.view');
        $exam->load(['session:id,name', 'term:id,name', 'gradingScale:id,name']);

        $papers = $exam->subjects()->with(['subject:id,name,code,type', 'schoolClass:id,name,numeric_order', 'studyGroup:id,name'])->get();
        $classes = $papers->pluck('schoolClass')->unique('id')->sortBy('numeric_order')->values();
        $classId = $request->integer('class_id') ?: $classes->first()?->id;

        return view('branch.exams.show', [
            'exam' => $exam,
            'classes' => $classes,
            'classId' => $classId,
            'papers' => $papers->where('school_class_id', $classId)->values(),
            'allClasses' => SchoolClass::query()->active()->ordered()->whereNotIn('id', $classes->pluck('id'))->pluck('name', 'id'),
            'transitions' => ExaminationService::MANUAL_TRANSITIONS[$exam->status] ?? [],
        ]);
    }

    public function addClass(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('examinations.update');
        $data = $request->validate(['school_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)]]);
        $class = SchoolClass::query()->findOrFail($data['school_class_id']);

        $added = $this->service->addClass($exam, $class);

        return redirect()->route('branch.exams.show', [$exam, 'class_id' => $class->id])->with('success', __(':count subject paper(s) added for :class.', ['count' => $added, 'class' => $class->name]));
    }

    public function updateClass(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('examinations.update');
        abort_if(in_array($exam->status, ['approved', 'published'], true), 422);

        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'rows' => ['required', 'array'],
            'rows.*.exam_date' => ['nullable', 'date'],
            'rows.*.starts_at' => ['nullable', 'date_format:H:i'],
            'rows.*.ends_at' => ['nullable', 'date_format:H:i'],
            'rows.*.pass_marks' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_optional' => ['nullable', 'boolean'],
            'rows.*.counts_in_gpa' => ['nullable', 'boolean'],
            'rows.*.components.*.full' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'rows.*.components.*.pass' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $this->service->updateClass($exam, (int) $data['school_class_id'], $data['rows']);

        return back()->with('success', __('Papers and schedule saved.'));
    }

    public function removePaper(Examination $exam, ExaminationSubject $paper): RedirectResponse
    {
        $this->authorize('examinations.update');
        abort_unless($paper->examination_id === $exam->id, 404);
        abort_unless(in_array($exam->status, ['draft', 'scheduled'], true), 422, __('Papers can only be removed before marks entry starts.'));

        $paper->delete();

        return back()->with('success', __('Paper removed.'));
    }

    public function changeStatus(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('examinations.update');
        $data = $request->validate(['status' => ['required', Rule::in(Examination::STATUSES)]]);
        $this->service->changeStatus($exam, $data['status']);

        return back()->with('success', __('Exam status changed to :status.', ['status' => __(ucfirst(str_replace('_', ' ', $data['status'])))]));
    }

    /** Printable exam routine for all classes. */
    public function routine(Examination $exam): View
    {
        $this->authorize('examinations.view');

        return view('branch.exams.routine', [
            'exam' => $exam->load('session:id,name'),
            'papers' => $exam->subjects()->with(['subject:id,name,code', 'schoolClass:id,name,numeric_order', 'studyGroup:id,name'])->get()
                ->sortBy(fn ($p) => sprintf('%05d-%s-%s', $p->schoolClass?->numeric_order ?? 0, $p->exam_date?->toDateString() ?? '9999', $p->starts_at))
                ->groupBy('school_class_id'),
        ]);
    }

    public function destroy(Examination $exam): RedirectResponse
    {
        $this->authorize('examinations.delete');
        abort_unless($exam->status === 'draft', 422, __('Only draft exams can be deleted.'));
        $exam->delete();

        return redirect()->route('branch.exams.index')->with('success', __('Exam deleted.'));
    }

    protected function validated(Request $request, ?Examination $exam = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::in(Examination::TYPES)],
            'academic_session_id' => ['required', 'integer', TenantRule::exists('academic_sessions')],
            'term_id' => ['nullable', 'integer', TenantRule::exists('terms')],
            'grading_scale_id' => ['required', 'integer', TenantRule::exists('grading_scales', 'id', true)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'min_attendance' => ['nullable', 'integer', 'min:0', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    protected function options(): array
    {
        return [
            'sessions' => AcademicSession::query()->latest('starts_on')->pluck('name', 'id'),
            'terms' => Term::query()->orderBy('sort_order')->get(['id', 'name', 'academic_session_id']),
            'scales' => GradingScale::query()->orderByDesc('is_default')->pluck('name', 'id'),
        ];
    }
}
