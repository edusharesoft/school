<?php

namespace App\Http\Controllers\Branch\Learning;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\SchoolClass;
use App\Models\Learning\Quiz;
use App\Models\Learning\QuizQuestion;
use App\Services\Learning\LearningAccess;
use App\Services\Learning\QuizService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Quizzes — back office and teacher portal (teachers see only their own). Permission: quizzes.* */
class QuizController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected QuizService $service, protected LearningAccess $access) {}

    public function index(Request $request): View
    {
        $this->authorize('quizzes.view');
        $quizzes = $this->access->scopeFor($request->user(), Quiz::query())
            ->with(['schoolClass:id,name', 'section:id,name', 'subject:id,name,name_bn', 'creator:id,name'])
            ->withCount(['questions', 'attempts as finished_attempts_count' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->when(in_array($request->input('status'), ['draft', 'published', 'closed'], true), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->integer('class_id'), fn ($q, $c) => $q->where('school_class_id', $c))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where('title', 'like', "%$t%"))
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'published' THEN 1 ELSE 2 END")->latest('id')->paginate(20)->withQueryString();

        return view('branch.learning.quizzes.index', [
            'quizzes' => $quizzes, 'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
        ] + $this->shared($request));
    }

    public function create(Request $request): View
    {
        $this->authorize('quizzes.create');

        return view('branch.learning.quizzes.form', $this->formData($request, new Quiz(['max_attempts' => 1, 'show_answers' => 'after_close', 'time_limit_minutes' => 20])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('quizzes.create');
        $quiz = $this->service->save($this->validated($request), $request->input('questions', []), $request->user());
        if ($request->boolean('publish_now')) {
            $this->service->publish($quiz, $request->user());
        }

        return redirect()->route($this->routePrefix($request).'learning.quizzes.show', $quiz)
            ->with('success', $quiz->status === 'published' ? __('Quiz published and students notified.') : __('Saved as a draft.'));
    }

    public function show(Request $request, Quiz $quiz): View
    {
        $this->assertView($request, $quiz);

        return view('branch.learning.quizzes.show', [
            'quiz' => $quiz->load(['schoolClass:id,name', 'section:id,name', 'subject:id,name,name_bn', 'creator:id,name', 'questions']),
            'results' => $quiz->status === 'draft' ? null : $this->service->results($quiz),
            'canManage' => $request->user()->can('quizzes.update') && $this->access->manages($request->user(), $quiz),
            'locked' => $quiz->attempts()->exists(),
        ] + $this->shared($request));
    }

    public function edit(Request $request, Quiz $quiz): View
    {
        $this->assertManage($request, $quiz);

        return view('branch.learning.quizzes.form', $this->formData($request, $quiz->load('questions')));
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->assertManage($request, $quiz);
        $locked = $quiz->attempts()->exists();
        $this->service->save($this->validated($request, $quiz), $locked ? null : $request->input('questions', []), $request->user(), $quiz);

        return redirect()->route($this->routePrefix($request).'learning.quizzes.show', $quiz)->with('success', __('Saved.'));
    }

    public function destroy(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorize('quizzes.delete');
        abort_unless($this->access->manages($request->user(), $quiz), 403);
        abort_if($quiz->attempts()->exists(), 403, __('Students have taken this quiz. Close it instead of deleting it.'));
        $quiz->delete();

        return redirect()->route($this->routePrefix($request).'learning.quizzes.index')->with('success', __('Deleted.'));
    }

    public function publish(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->assertManage($request, $quiz);
        $this->service->publish($quiz, $request->user());

        return back()->with('success', __('Quiz published and students notified.'));
    }

    public function close(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->assertManage($request, $quiz);
        $this->service->close($quiz);

        return back()->with('success', __('Quiz closed.'));
    }

    public function reopen(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->assertManage($request, $quiz);
        $this->service->reopen($quiz);

        return back()->with('success', __('Reopened.'));
    }

    // ---- helpers ----------------------------------------------------------------------------------------------

    protected function assertView(Request $request, Quiz $quiz): void
    {
        $this->authorize('quizzes.view');
        abort_unless($this->access->manages($request->user(), $quiz) || ! $this->access->isTeacher($request->user()), 404);
    }

    protected function assertManage(Request $request, Quiz $quiz): void
    {
        $this->authorize('quizzes.update');
        abort_unless($this->access->manages($request->user(), $quiz), 404);
    }

    protected function validated(Request $request, ?Quiz $quiz = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)],
            'section_id' => ['nullable', 'integer', TenantRule::exists('sections', 'id', true)],
            'subject_id' => ['nullable', 'integer', TenantRule::exists('subjects', 'id', true)],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'show_answers' => ['required', Rule::in(Quiz::REVIEW)],
            'pass_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'questions' => ['nullable', 'array', 'max:100'],
            'questions.*.type' => ['nullable', Rule::in(QuizQuestion::TYPES)],
            'questions.*.text' => ['nullable', 'string', 'max:2000'],
            'questions.*.options' => ['nullable', 'array', 'max:8'],
            'questions.*.options.*' => ['nullable', 'string', 'max:500'],
            'questions.*.correct' => ['nullable', 'array'],
            'questions.*.correct.*' => ['integer', 'min:0', 'max:7'],
            'questions.*.marks' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    protected function formData(Request $request, Quiz $quiz): array
    {
        $questions = old('questions') ?? $quiz->questions->map(fn ($q) => [
            'type' => $q->type, 'text' => $q->text, 'options' => $q->options, 'correct' => $q->correct, 'marks' => $q->marks + 0, 'explanation' => $q->explanation,
        ])->values()->all();

        return [
            'quiz' => $quiz, 'questionsData' => $questions, 'locked' => $quiz->exists && $quiz->attempts()->exists(),
            'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
            'sections' => $this->access->sections($request->user())->map(fn ($s) => ['id' => $s->id, 'class' => $s->school_class_id, 'name' => $s->name])->values(),
            'subjects' => ClassSubject::query()->with('subject:id,name,name_bn')->get()->map(fn ($c) => ['class' => $c->school_class_id, 'id' => $c->subject_id, 'name' => $c->subject?->displayName()])
                ->filter(fn ($r) => $r['name'])->unique(fn ($r) => $r['class'].'-'.$r['id'])->values(),
            'sectionRequired' => $this->access->isTeacher($request->user()),
        ] + $this->shared($request);
    }

    protected function shared(Request $request): array
    {
        return ['layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request)];
    }
}
