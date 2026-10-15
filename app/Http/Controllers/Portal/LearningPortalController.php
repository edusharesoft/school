<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Learning\Assignment;
use App\Models\Learning\AssignmentSubmission;
use App\Models\Learning\LearningMaterial;
use App\Models\Learning\OnlineClass;
use App\Models\Students\Student;
use App\Services\Learning\AssignmentService;
use App\Services\Learning\LearningProgress;
use App\Services\Portal\PortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Learning in the student and parent portals: homework & assignments (students hand in, parents follow),
 * learning materials and learning progress. Parents only ever see their own children.
 */
class LearningPortalController extends Controller
{
    public function __construct(protected PortalService $portal, protected LearningProgress $progress, protected AssignmentService $assignments) {}

    // ------------------------------------------------------------ student

    public function studentProgress(Request $request): View
    {
        return $this->progressPage($this->student($request), 'portal.student.', 'student');
    }

    public function studentAssignment(Request $request, Assignment $assignment): View
    {
        return $this->assignmentPage($this->student($request), $assignment, 'portal.student.', 'student');
    }

    public function studentSubmit(Request $request, Assignment $assignment): RedirectResponse
    {
        $student = $this->student($request);
        $this->assertOwn($student, $assignment);
        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,png,jpg,jpeg,webp,zip', 'max:10240'],
        ]);
        $this->assignments->submit($assignment, $student, $data['text'] ?? null, $request->file('file'));

        return redirect()->route('portal.student.assignments.show', $assignment)->with('success', __('Your work has been handed in.'));
    }

    public function studentAttachment(Request $request, Assignment $assignment): StreamedResponse
    {
        $this->assertOwn($this->student($request), $assignment);

        return $this->download($assignment->attachment_path, $assignment->attachment_name);
    }

    public function studentSubmissionFile(Request $request, Assignment $assignment): StreamedResponse
    {
        $student = $this->student($request);
        $this->assertOwn($student, $assignment);
        $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $student->id)->firstOrFail();

        return $this->download($submission->file_path, $submission->file_name);
    }

    public function studentMaterials(Request $request): View
    {
        return $this->materialsPage($this->student($request), 'portal.student.', 'student');
    }

    public function studentMaterialFile(Request $request, LearningMaterial $material): StreamedResponse
    {
        $this->assertMaterial($this->student($request), $material);

        return $this->download($material->file_path, $material->file_name);
    }

    public function studentOnlineClasses(Request $request): View
    {
        return $this->onlineClassesPage($this->student($request), 'portal.student.', 'student');
    }

    // ------------------------------------------------------------ parent

    public function parentProgress(Request $request, Student $student): View
    {
        $this->assertChild($request, $student);

        return $this->progressPage($student, 'portal.parent.', 'parent');
    }

    public function parentAssignment(Request $request, Student $student, Assignment $assignment): View
    {
        $this->assertChild($request, $student);

        return $this->assignmentPage($student, $assignment, 'portal.parent.', 'parent');
    }

    public function parentAttachment(Request $request, Student $student, Assignment $assignment): StreamedResponse
    {
        $this->assertChild($request, $student);
        $this->assertOwn($student, $assignment);

        return $this->download($assignment->attachment_path, $assignment->attachment_name);
    }

    public function parentSubmissionFile(Request $request, Student $student, Assignment $assignment): StreamedResponse
    {
        $this->assertChild($request, $student);
        $this->assertOwn($student, $assignment);
        $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $student->id)->firstOrFail();

        return $this->download($submission->file_path, $submission->file_name);
    }

    public function parentMaterials(Request $request, Student $student): View
    {
        $this->assertChild($request, $student);

        return $this->materialsPage($student, 'portal.parent.', 'parent');
    }

    public function parentMaterialFile(Request $request, Student $student, LearningMaterial $material): StreamedResponse
    {
        $this->assertChild($request, $student);
        $this->assertMaterial($student, $material);

        return $this->download($material->file_path, $material->file_name);
    }

    public function parentOnlineClasses(Request $request, Student $student): View
    {
        $this->assertChild($request, $student);

        return $this->onlineClassesPage($student, 'portal.parent.', 'parent');
    }

    // ------------------------------------------------------------ shared

    /** Upcoming classes (join link only from 15 minutes before the start) and the last month's classes with recordings. */
    protected function onlineClassesPage(Student $student, string $routePrefix, string $mode): View
    {
        $enrollment = $student->currentEnrollment;
        $upcoming = $past = collect();
        if ($enrollment) {
            $upcoming = OnlineClass::query()->forEnrollment($enrollment)->with(['subject:id,name,name_bn', 'teacher:id,name'])
                ->where('status', 'scheduled')->where('ends_at', '>=', now())->orderBy('starts_at')->limit(50)->get();
            $past = OnlineClass::query()->forEnrollment($enrollment)->with(['subject:id,name,name_bn', 'teacher:id,name'])
                ->where(fn ($q) => $q->where('status', '!=', 'scheduled')->orWhere('ends_at', '<', now()))
                ->where('starts_at', '>=', now()->subDays(30))->orderByDesc('starts_at')->limit(50)->get();
        }
        $attendance = $enrollment && $past->isNotEmpty()
            ? \App\Models\Learning\OnlineClassAttendance::query()->where('student_id', $student->id)->whereIn('online_class_id', $past->pluck('id'))->pluck('status', 'online_class_id')
            : collect();

        return view('portal.learning.online-classes', [
            'student' => $student, 'upcoming' => $upcoming, 'past' => $past, 'attendance' => $attendance,
            'routePrefix' => $routePrefix, 'mode' => $mode, 'routeParams' => $mode === 'parent' ? [$student] : [],
        ]);
    }

    protected function progressPage(Student $student, string $routePrefix, string $mode): View
    {
        return view('portal.learning.progress', [
            'student' => $student, 'progress' => $this->progress->forStudent($student),
            'routePrefix' => $routePrefix, 'mode' => $mode, 'routeParams' => $mode === 'parent' ? [$student] : [],
        ]);
    }

    protected function assignmentPage(Student $student, Assignment $assignment, string $routePrefix, string $mode): View
    {
        $this->assertOwn($student, $assignment);
        $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $student->id)->first();

        return view('portal.learning.assignment', [
            'student' => $student, 'assignment' => $assignment->load(['subject:id,name,name_bn', 'schoolClass:id,name', 'section:id,name', 'creator:id,name']),
            'submission' => $submission, 'canSubmit' => $mode === 'student' && $assignment->acceptsWorkFrom($submission),
            'routePrefix' => $routePrefix, 'mode' => $mode, 'routeParams' => $mode === 'parent' ? [$student] : [],
        ]);
    }

    protected function materialsPage(Student $student, string $routePrefix, string $mode): View
    {
        $enrollment = $student->currentEnrollment;
        $materials = $enrollment
            ? LearningMaterial::query()->published()->forEnrollment($enrollment)->with(['subject:id,name,name_bn'])->latest('published_at')->latest('id')->get()
            : collect();

        return view('portal.learning.materials', [
            'student' => $student, 'materials' => $materials->groupBy(fn ($m) => $m->subject?->displayName() ?? __('General')),
            'routePrefix' => $routePrefix, 'mode' => $mode, 'routeParams' => $mode === 'parent' ? [$student] : [],
        ]);
    }

    /** The assignment must be visible to this student: published/closed, same session, class and section. */
    protected function assertOwn(Student $student, Assignment $assignment): void
    {
        $enrollment = $student->currentEnrollment;
        abort_unless($enrollment && Assignment::query()->visibleToStudents()->forEnrollment($enrollment)->whereKey($assignment->id)->exists(), 404);
    }

    protected function assertMaterial(Student $student, LearningMaterial $material): void
    {
        $enrollment = $student->currentEnrollment;
        abort_unless($enrollment && LearningMaterial::query()->published()->forEnrollment($enrollment)->whereKey($material->id)->exists(), 404);
    }

    protected function download(?string $path, ?string $name): StreamedResponse
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name);
    }

    protected function student(Request $request): Student
    {
        $student = $this->portal->studentFor($request->user());
        abort_unless($student, 403, __('Your login is not linked to a student record. Please contact the school office.'));

        return $student;
    }

    protected function assertChild(Request $request, Student $student): void
    {
        $guardian = $this->portal->guardianFor($request->user());
        abort_unless($guardian && $this->portal->isChildOf($guardian, $student), 404);
    }
}
