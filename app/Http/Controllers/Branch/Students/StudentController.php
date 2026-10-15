<?php

namespace App\Http\Controllers\Branch\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StudentRequest;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\StudyGroup;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\StudentDocument;
use App\Services\Students\StudentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(protected StudentService $students) {}

    /** Search by name / ID / roll / class / section / session / status (master prompt §9). */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Student::class);

        $sessions = AcademicSession::query()->latest('starts_on')->get(['id', 'name', 'is_current']);
        $sessionId = $request->integer('session_id') ?: $sessions->firstWhere('is_current', true)?->id;

        $students = $this->filteredQuery($request, $sessionId)
            ->with(['enrollments' => fn ($q) => $q->where('academic_session_id', $sessionId)->with(['schoolClass:id,name', 'section:id,name', 'studyGroup:id,name'])])
            ->paginate(25)
            ->withQueryString();

        return view('branch.students.index', [
            'students' => $students,
            'sessions' => $sessions,
            'sessionId' => $sessionId,
            'classes' => SchoolClass::query()->ordered()->get(['id', 'name']),
            'sections' => $request->integer('class_id')
                ? Section::query()->where('school_class_id', $request->integer('class_id'))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Student::class);

        return view('branch.students.create', $this->formOptions() + ['student' => new Student(['nationality' => 'Bangladeshi', 'admission_date' => now()])]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = $this->students->admit($request->studentData(), $request->validated('guardians'), $request->enrollmentData());
        $this->storePhoto($request, $student);

        return redirect()->route('branch.students.show', $student)
            ->with('success', __('Student admitted. ID: :code', ['code' => $student->student_code]));
    }

    public function show(Request $request, Student $student): View
    {
        $this->authorize('view', $student);

        $student->load([
            'guardians',
            'enrollments' => fn ($q) => $q->with(['session:id,name', 'schoolClass:id,name', 'section:id,name', 'studyGroup:id,name']),
            'documents.uploader:id,name',
        ]);

        return view('branch.students.show', [
            'student' => $student,
            'canSeeHealth' => $request->user()->can('viewHealth', $student),
            'documentTypes' => StudentDocument::TYPES,
            'relations' => Guardian::RELATIONS,
            'certificates' => $student->certificates()->with('issuer:id,name')->latest('id')->get(),
            'otherBranches' => \App\Models\Branch::query()->where('institution_id', $student->institution_id)
                ->whereKeyNot($student->branch_id)->active()->ordered()->pluck('name', 'id'),
        ] + $this->formOptions());
    }

    public function edit(Student $student): View
    {
        $this->authorize('update', $student);

        return view('branch.students.edit', ['student' => $student]);
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->studentData();
        if (! $request->user()->can('viewHealth', $student)) {
            unset($data['medical_notes']); // cannot overwrite what you cannot see
        }

        $student->update($data);
        $this->storePhoto($request, $student);

        return redirect()->route('branch.students.show', $student)->with('success', __('Student profile updated.'));
    }

    public function changeStatus(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('changeStatus', $student);

        $data = $request->validate([
            'status' => ['required', Rule::in(Student::STATUSES)],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $this->students->changeStatus($student, $data['status'], $data['date'] ?? null);

        return back()->with('success', __('Student status changed to :status.', ['status' => __(ucfirst($data['status']))]));
    }

    /** Re-admission of a withdrawn / transferred / suspended student into a session (same student ID). */
    public function readmit(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('changeStatus', $student);

        $data = $request->validate([
            'academic_session_id' => ['required', 'integer', \App\Support\TenantRule::exists('academic_sessions')],
            'school_class_id' => ['required', 'integer', \App\Support\TenantRule::exists('school_classes', 'id', true)],
            'section_id' => ['required', 'integer', \App\Support\TenantRule::exists('sections', 'id', true)],
            'study_group_id' => ['nullable', 'integer', \App\Support\TenantRule::exists('study_groups')],
            'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $this->students->readmit($student, $data);

        return back()->with('success', __('Student re-admitted.'));
    }

    /** Photos live on the private disk; served only after authorization. */
    public function photo(Student $student): StreamedResponse
    {
        $this->authorize('view', $student);
        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return Storage::disk('local')->response($student->photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function filteredQuery(Request $request, ?int $sessionId): Builder
    {
        $classId = $request->integer('class_id');
        $sectionId = $request->integer('section_id');
        $roll = $request->integer('roll_no');
        $status = $request->string('status')->toString() ?: 'active';

        return Student::query()
            ->search($request->string('q')->toString())
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($classId || $sectionId || $roll, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e
                ->where('academic_session_id', $sessionId)
                ->when($classId, fn ($e) => $e->where('school_class_id', $classId))
                ->when($sectionId, fn ($e) => $e->where('section_id', $sectionId))
                ->when($roll, fn ($e) => $e->where('roll_no', $roll))))
            ->orderBy('name');
    }

    protected function formOptions(): array
    {
        return [
            'sessions' => AcademicSession::query()->where('status', 'open')->latest('starts_on')->get(['id', 'name', 'is_current']),
            'classes' => SchoolClass::query()->active()->ordered()->with(['sections' => fn ($q) => $q->where('is_active', true)])->get(),
            'groups' => StudyGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'relations' => Guardian::RELATIONS,
        ];
    }

    protected function storePhoto(StudentRequest $request, Student $student): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $old = $student->photo_path;
        $path = $request->file('photo')->store(tenant()->branch()->storagePath('students/'.$student->id), 'local');
        $student->forceFill(['photo_path' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
    }
}
