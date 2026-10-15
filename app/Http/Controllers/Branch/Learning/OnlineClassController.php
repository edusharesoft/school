<?php

namespace App\Http\Controllers\Branch\Learning;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\SchoolClass;
use App\Models\Learning\OnlineClass;
use App\Models\Learning\OnlineClassAttendance;
use App\Services\Academic\TimetableService;
use App\Services\Learning\LearningAccess;
use App\Services\Learning\Meetings\MeetingProviders;
use App\Services\Learning\OnlineClassService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Online / offline class schedule (permission: online-classes.*). Shared by the back office and the teacher portal. */
class OnlineClassController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected LearningAccess $access, protected OnlineClassService $classes, protected MeetingProviders $providers) {}

    public function index(Request $request): View
    {
        $this->authorize('online-classes.view');
        $when = $request->input('when', 'upcoming');
        $items = $this->access->scopeForHost($request->user(), OnlineClass::query())
            ->with(['schoolClass:id,name', 'section:id,name', 'subject:id,name,name_bn', 'teacher:id,name'])
            ->when($when === 'upcoming', fn ($q) => $q->where('status', 'scheduled')->where('ends_at', '>=', now())->orderBy('starts_at'))
            ->when($when === 'past', fn ($q) => $q->where(fn ($w) => $w->where('status', '!=', 'scheduled')->orWhere('ends_at', '<', now()))->orderByDesc('starts_at'))
            ->when($when === 'all', fn ($q) => $q->orderByDesc('starts_at'))
            ->when(in_array($request->input('mode'), OnlineClass::MODES, true), fn ($q) => $q->where('mode', $request->input('mode')))
            ->when($request->integer('class_id'), fn ($q, $c) => $q->where('school_class_id', $c))
            ->paginate(25)->withQueryString();

        return view('branch.learning.online-classes.index', [
            'items' => $items, 'when' => $when,
            'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
        ] + $this->shared($request));
    }

    public function create(Request $request): View
    {
        $this->authorize('online-classes.create');

        return view('branch.learning.online-classes.form', $this->formData($request, new OnlineClass([
            'mode' => 'online', 'provider' => 'manual',
            'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(10, (int) config('online_classes.default_duration_minutes', 45)),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('online-classes.create');
        $result = $this->classes->save($this->validated($request), $request->user());

        return $this->back($request, $result['class'], __('Class scheduled.'), $result['warning']);
    }

    public function show(Request $request, OnlineClass $onlineClass): View
    {
        $this->authorize('online-classes.view');
        $this->assertVisible($request, $onlineClass);
        $onlineClass->load(['schoolClass:id,name', 'section:id,name', 'subject:id,name,name_bn', 'teacher:id,name', 'creator:id,name']);
        $roster = $this->classes->roster($onlineClass);

        return view('branch.learning.online-classes.show', [
            'class' => $onlineClass, 'roster' => $roster,
            'counts' => $roster->countBy(fn ($r) => $r['status'] ?? 'unmarked'),
            'canManage' => $request->user()->can('online-classes.update') && $this->access->manages($request->user(), $onlineClass),
        ] + $this->shared($request));
    }

    public function edit(Request $request, OnlineClass $onlineClass): View
    {
        $this->assertManage($request, $onlineClass);
        abort_unless($onlineClass->status === 'scheduled', 403, __('Only a scheduled class can be changed.'));

        return view('branch.learning.online-classes.form', $this->formData($request, $onlineClass));
    }

    public function update(Request $request, OnlineClass $onlineClass): RedirectResponse
    {
        $this->assertManage($request, $onlineClass);
        $result = $this->classes->save($this->validated($request), $request->user(), $onlineClass);

        return $this->back($request, $result['class'], __('Class updated.'), $result['warning']);
    }

    public function cancel(Request $request, OnlineClass $onlineClass): RedirectResponse
    {
        $this->assertManage($request, $onlineClass);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $warning = $this->classes->cancel($onlineClass, $request->user(), $data['reason'] ?? null);

        return $this->back($request, $onlineClass, __('Class cancelled and students notified.'), $warning);
    }

    public function complete(Request $request, OnlineClass $onlineClass): RedirectResponse
    {
        $this->assertManage($request, $onlineClass);
        $warning = $this->classes->complete($onlineClass, $request->user());

        return $this->back($request, $onlineClass, __('Class marked as completed.'), $warning);
    }

    /** Retry creating / updating the Zoom or Google Meet meeting after a failure. */
    public function sync(Request $request, OnlineClass $onlineClass): RedirectResponse
    {
        $this->assertManage($request, $onlineClass);
        abort_unless($onlineClass->status === 'scheduled', 403);
        $warning = $this->classes->sync($onlineClass);

        return $this->back($request, $onlineClass, __('Meeting synced with :provider.', ['provider' => $onlineClass->providerLabel()]), $warning);
    }

    /** Save a pasted recording link, or (Zoom) fetch the cloud recording. */
    public function recording(Request $request, OnlineClass $onlineClass): RedirectResponse
    {
        $this->assertManage($request, $onlineClass);
        abort_if($onlineClass->status === 'cancelled', 403);
        if ($request->boolean('fetch')) {
            $warning = $this->classes->fetchRecording($onlineClass->refresh());

            return $this->back($request, $onlineClass, $onlineClass->recording_url ? __('Recording link saved.') : __('No recording is available yet.'), $warning);
        }
        $data = $request->validate(['recording_url' => ['nullable', 'url:http,https', 'max:500']]);
        $this->classes->setRecording($onlineClass, $data['recording_url'] ?? null, $request->user());

        return $this->back($request, $onlineClass, __('Recording link saved.'));
    }

    public function attendance(Request $request, OnlineClass $onlineClass): RedirectResponse
    {
        $this->assertManage($request, $onlineClass);
        abort_if($onlineClass->status === 'cancelled', 403);
        $data = $request->validate([
            'status' => ['required', 'array'],
            'status.*' => ['nullable', Rule::in(OnlineClassAttendance::STATUSES)],
        ]);
        $saved = $this->classes->markAttendance($onlineClass, $data['status'], $request->user());

        return $this->back($request, $onlineClass, __(':n attendance marks saved.', ['n' => $saved]));
    }

    // ---- helpers ----------------------------------------------------------------------------------------------

    protected function back(Request $request, OnlineClass $class, string $success, ?string $warning = null): RedirectResponse
    {
        $redirect = redirect()->route($this->routePrefix($request).'learning.online-classes.show', $class)->with('success', $success);

        return $warning ? $redirect->with('warning', $warning) : $redirect;
    }

    protected function assertVisible(Request $request, OnlineClass $class): void
    {
        abort_unless($this->access->manages($request->user(), $class), 404);
    }

    protected function assertManage(Request $request, OnlineClass $class): void
    {
        $this->authorize('online-classes.update');
        $this->assertVisible($request, $class);
    }

    protected function validated(Request $request): array
    {
        $providers = array_intersect(OnlineClass::PROVIDERS, ['zoom', 'google_meet', 'manual']);
        $data = $request->validate([
            'mode' => ['required', Rule::in(OnlineClass::MODES)],
            'provider' => ['nullable', Rule::requiredIf($request->input('mode') === 'online'), Rule::in($providers)],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)],
            'section_id' => ['nullable', 'integer', TenantRule::exists('sections', 'id', true)],
            'subject_id' => ['nullable', 'integer', TenantRule::exists('subjects', 'id', true)],
            'teacher_id' => ['nullable', 'integer', Rule::in($this->teacherIds())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'room' => ['nullable', 'string', 'max:120'],
            'join_url' => ['nullable', 'url:http,https', 'max:500'],
            'meeting_id' => ['nullable', 'string', 'max:120'],
            'passcode' => ['nullable', 'string', 'max:120'],
        ]);
        abort_if(\Illuminate\Support\Carbon::parse($data['ends_at'])->diffInHours($data['starts_at'], true) > 12, 422, __('A class cannot be longer than 12 hours.'));

        return $data;
    }

    /** @return int[] */
    protected function teacherIds(): array
    {
        return app(TimetableService::class)->teachers()->keys()->map(fn ($k) => (int) $k)->all();
    }

    protected function formData(Request $request, OnlineClass $class): array
    {
        $backOffice = ! $this->access->isTeacher($request->user());

        return [
            'class' => $class,
            'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
            'sections' => $this->access->sections($request->user())->map(fn ($s) => ['id' => $s->id, 'class' => $s->school_class_id, 'name' => $s->name])->values(),
            'subjects' => ClassSubject::query()->with('subject:id,name,name_bn')->get()->map(fn ($c) => ['class' => $c->school_class_id, 'id' => $c->subject_id, 'name' => $c->subject?->displayName()])
                ->filter(fn ($r) => $r['name'])->unique(fn ($r) => $r['class'].'-'.$r['id'])->values(),
            'sectionRequired' => $this->access->isTeacher($request->user()),
            'teachers' => $backOffice ? app(TimetableService::class)->teachers() : collect(),
            'providers' => $this->providers->available(),
        ] + $this->shared($request);
    }

    protected function shared(Request $request): array
    {
        return ['layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request)];
    }
}
