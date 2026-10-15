<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ClassSubjectRequest;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\StudyGroup;
use App\Models\Academic\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Subject combinations for one class (compulsory / optional 4th subject / elective, per group). */
class ClassSubjectController extends Controller
{
    public function index(SchoolClass $class): View
    {
        $this->authorize('view', $class);

        return view('branch.academics.classes.subjects', [
            'class' => $class,
            'rows' => $class->classSubjects()->with(['subject', 'studyGroup'])->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('code')->get(['id', 'name', 'code']),
            'groups' => StudyGroup::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(ClassSubjectRequest $request, SchoolClass $class): RedirectResponse
    {
        $class->classSubjects()->create($request->validated());

        return back()->with('success', __('Subject added to class.'));
    }

    public function update(ClassSubjectRequest $request, SchoolClass $class, ClassSubject $classSubject): RedirectResponse
    {
        abort_unless($classSubject->school_class_id === $class->id, 404);
        $classSubject->update($request->validated());

        return back()->with('success', __('Subject updated.'));
    }

    public function destroy(SchoolClass $class, ClassSubject $classSubject): RedirectResponse
    {
        $this->authorize('update', $class);
        abort_unless($classSubject->school_class_id === $class->id, 404);

        $classSubject->delete();

        return back()->with('success', __('Subject removed from class.'));
    }
}
