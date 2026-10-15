<?php

namespace App\Http\Controllers\Branch\Learning;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Models\Students\Student;
use App\Services\Learning\LearningAccess;
use App\Services\Learning\LearningProgress;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Learning progress of a section (hand-in discipline, marks) and of one student. Teachers: their own sections. */
class ProgressController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected LearningProgress $progress, protected LearningAccess $access) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('assignments.view') || $request->user()->can('homework.view'), 403);
        $sections = $this->access->sections($request->user());
        $sectionId = $request->integer('section_id') ?: $sections->first()?->id;
        abort_if($sectionId && ! $sections->contains('id', $sectionId), 404);

        return view('branch.learning.progress', [
            'sections' => $sections, 'sectionId' => $sectionId,
            'rows' => $sectionId ? $this->progress->forSection($sectionId) : collect(),
            'layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function student(Request $request, Student $student): View
    {
        abort_unless($request->user()->can('assignments.view') || $request->user()->can('homework.view'), 403);
        $sectionId = $student->currentEnrollment?->section_id;
        abort_unless($sectionId && $this->access->sectionIds($request->user())->contains($sectionId), 404);

        return view('branch.learning.student-progress', [
            'student' => $student, 'progress' => $this->progress->forStudent($student),
            'layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request),
        ]);
    }
}
