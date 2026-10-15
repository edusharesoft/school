<?php

namespace App\Http\Controllers\Branch\Learning;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\SchoolClass;
use App\Models\Learning\Assignment;
use App\Models\Learning\AssignmentSubmission;
use App\Services\Learning\AssignmentService;
use App\Services\Learning\LearningAccess;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Homework, assignments and projects — back office and teacher portal (same screens, teachers see only their own).
 * Permissions: homework.* for homework, assignments.* for assignments and projects; evaluating needs
 * assignments.evaluate (homework.update also evaluates homework).
 */
class AssignmentController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected AssignmentService $service, protected LearningAccess $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $kinds = array_values(array_filter(Assignment::KINDS, fn ($k) => $user->can($this->permission($k).'.view')));
        abort_if($kinds === [], 403);
        $kind = $request->input('kind');

        $assignments = $this->access->scopeFor($user, Assignment::query())
            ->whereIn('kind', $kinds)
            ->with(['schoolClass:id,name', 'section:id,name', 'subject:id,name,name_bn', 'creator:id,name'])
            ->withCount([
                'submissions as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at'),
                'submissions as evaluated_count' => fn ($q) => $q->where('status', 'evaluated'),
            ])
            ->when(in_array($kind, $kinds, true), fn ($q) => $q->where('kind', $kind))
            ->when(in_array($request->input('status'), ['draft', 'published', 'closed'], true), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->integer('class_id'), fn ($q, $c) => $q->where('school_class_id', $c))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where('title', 'like', "%$t%"))
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'published' THEN 1 ELSE 2 END")->orderByDesc('due_at')->paginate(20)->withQueryString();

        return view('branch.learning.assignments.index', [
            'assignments' => $assignments, 'kinds' => $kinds, 'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
            'audiences' => $this->audienceCounts($assignments->getCollection()),
        ] + $this->shared($request));
    }

    public function create(Request $request): View
    {
        $kind = in_array($request->input('kind'), Assignment::KINDS, true) ? $request->input('kind') : 'homework';
        $this->authorize($this->permission($kind).'.create');

        return view('branch.learning.assignments.form', $this->formData($request, new Assignment(['kind' => $kind, 'submission_mode' => 'either', 'due_at' => now()->addDays(3)->setTime(17, 0)])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorize($this->permission($data['kind']).'.create');
        $assignment = $this->service->save($data, $request->file('attachment'), $request->user());
        if ($request->boolean('publish_now')) {
            $this->service->publish($assignment, $request->user());
        }

        return redirect()->route($this->routePrefix($request).'learning.assignments.show', $assignment)
            ->with('success', $assignment->status === 'published' ? __('Published and students notified.') : __('Saved as a draft.'));
    }

    public function show(Request $request, Assignment $assignment): View
    {
        $this->assertView($request, $assignment);
        $roster = $assignment->status === 'draft' ? collect() : $this->service->roster($assignment);

        return view('branch.learning.assignments.show', [
            'assignment' => $assignment->load(['schoolClass:id,name', 'section:id,name', 'subject:id,name,name_bn', 'creator:id,name']),
            'roster' => $roster, 'canEvaluate' => $this->canEvaluate($request, $assignment),
            'canManage' => $request->user()->can($this->permission($assignment->kind).'.update') && $this->access->manages($request->user(), $assignment),
        ] + $this->shared($request));
    }

    public function edit(Request $request, Assignment $assignment): View
    {
        $this->assertManage($request, $assignment);

        return view('branch.learning.assignments.form', $this->formData($request, $assignment));
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->assertManage($request, $assignment);
        $this->service->save($this->validated($request, $assignment), $request->file('attachment'), $request->user(), $assignment);

        return redirect()->route($this->routePrefix($request).'learning.assignments.show', $assignment)->with('success', __('Saved.'));
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorize($this->permission($assignment->kind).'.delete');
        abort_unless($this->access->manages($request->user(), $assignment), 403);
        abort_unless($assignment->status === 'draft' || ! $assignment->submissions()->exists(), 403, __('Work has been handed in. Close the assignment instead of deleting it.'));

        $this->service->deleteFiles($assignment);
        $assignment->submissions()->delete();
        $assignment->delete();

        return redirect()->route($this->routePrefix($request).'learning.assignments.index')->with('success', __('Deleted.'));
    }

    public function publish(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->assertManage($request, $assignment);
        $this->service->publish($assignment, $request->user());

        return back()->with('success', __('Published and students notified.'));
    }

    public function close(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->assertManage($request, $assignment);
        $this->service->close($assignment);

        return back()->with('success', __('Closed — no more hand-ins.'));
    }

    public function reopen(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->assertManage($request, $assignment);
        $this->service->reopen($assignment);

        return back()->with('success', __('Reopened.'));
    }

    public function evaluate(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($this->canEvaluate($request, $assignment), 403);
        $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.marks' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'rows.*.feedback' => ['nullable', 'string', 'max:2000'],
            'rows.*.action' => ['nullable', Rule::in(['evaluate', 'return', 'skip'])],
        ]);
        $stats = $this->service->evaluate($assignment, $request->input('rows'), $request->user());

        return back()->with('success', __(':n evaluated, :r returned for revision.', ['n' => $stats['evaluated'], 'r' => $stats['returned']]));
    }

    public function attachment(Request $request, Assignment $assignment): StreamedResponse
    {
        $this->assertView($request, $assignment);
        abort_unless($assignment->attachment_path && Storage::disk('local')->exists($assignment->attachment_path), 404);

        return Storage::disk('local')->download($assignment->attachment_path, $assignment->attachment_name);
    }

    public function submissionFile(Request $request, Assignment $assignment, AssignmentSubmission $submission): StreamedResponse
    {
        $this->assertView($request, $assignment);
        abort_unless($submission->assignment_id === $assignment->id && $submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download($submission->file_path, $submission->file_name);
    }

    // ---------------------------------------------------------------- helpers

    protected function permission(string $kind): string
    {
        return $kind === 'homework' ? 'homework' : 'assignments';
    }

    protected function assertView(Request $request, Assignment $assignment): void
    {
        $this->authorize($this->permission($assignment->kind).'.view');
        abort_unless($this->access->manages($request->user(), $assignment) || ! $this->access->isTeacher($request->user()), 404);
    }

    protected function assertManage(Request $request, Assignment $assignment): void
    {
        $this->authorize($this->permission($assignment->kind).'.update');
        abort_unless($this->access->manages($request->user(), $assignment), 404);
    }

    protected function canEvaluate(Request $request, Assignment $assignment): bool
    {
        $user = $request->user();

        return $this->access->manages($user, $assignment)
            && ($user->can('assignments.evaluate') || ($assignment->kind === 'homework' && $user->can('homework.update')));
    }

    protected function validated(Request $request, ?Assignment $assignment = null): array
    {
        return $request->validate([
            'kind' => [$assignment ? 'prohibited' : 'required', Rule::in(Assignment::KINDS)],
            'title' => ['required', 'string', 'max:191'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)],
            'section_id' => ['nullable', 'integer', TenantRule::exists('sections', 'id', true)],
            'subject_id' => ['nullable', 'integer', TenantRule::exists('subjects', 'id', true)],
            'due_at' => ['required', 'date', ! $assignment ? 'after:now' : 'date'],
            'max_marks' => ['nullable', 'numeric', 'min:0.5', 'max:9999'],
            'submission_mode' => ['required', Rule::in(Assignment::MODES)],
            'allow_late' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,png,jpg,jpeg,webp', 'max:10240'],
            'remove_attachment' => ['nullable', 'boolean'],
        ]) + ['kind' => $assignment?->kind];
    }

    protected function formData(Request $request, Assignment $assignment): array
    {
        $user = $request->user();

        return [
            'assignment' => $assignment,
            'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
            'sections' => $this->access->sections($user)->map(fn ($s) => ['id' => $s->id, 'class' => $s->school_class_id, 'name' => $s->name])->values(),
            'subjects' => ClassSubject::query()->with('subject:id,name,name_bn')->get()->map(fn ($c) => ['class' => $c->school_class_id, 'id' => $c->subject_id, 'name' => $c->subject?->displayName()])
                ->filter(fn ($r) => $r['name'])->unique(fn ($r) => $r['class'].'-'.$r['id'])->values(),
            'sectionRequired' => $this->access->isTeacher($user),
        ] + $this->shared($request);
    }

    protected function shared(Request $request): array
    {
        return ['layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request)];
    }

    protected function audienceCounts($assignments): array
    {
        return $assignments->mapWithKeys(fn (Assignment $a) => [$a->id => $a->status === 'draft' ? null : $a->audience()->count()])->all();
    }
}
