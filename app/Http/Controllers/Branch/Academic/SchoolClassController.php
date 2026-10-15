<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SchoolClassRequest;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SchoolClass::class);

        $session = AcademicSession::currentForBranch();

        $classes = SchoolClass::query()
            ->with(['sections.room', 'sections.classTeacher:id,name'])
            ->withCount(['classSubjects', 'enrollments as students_count' => fn ($q) => $q->where('status', 'enrolled')
                ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))])
            ->ordered()
            ->get();

        return view('branch.academics.classes.index', compact('classes', 'session'));
    }

    public function create(): View
    {
        $this->authorize('create', SchoolClass::class);

        $next = (int) SchoolClass::query()->max('numeric_order') + 1;

        return view('branch.academics.classes.form', ['class' => new SchoolClass(['level' => 'secondary', 'numeric_order' => $next, 'is_active' => true])]);
    }

    public function store(SchoolClassRequest $request): RedirectResponse
    {
        SchoolClass::query()->create($this->data($request));

        return redirect()->route('branch.academics.classes.index')->with('success', __('Class created.'));
    }

    public function edit(SchoolClass $class): View
    {
        $this->authorize('update', $class);

        return view('branch.academics.classes.form', compact('class'));
    }

    public function update(SchoolClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $class->update($this->data($request));

        return redirect()->route('branch.academics.classes.index')->with('success', __('Class updated.'));
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        $this->authorize('delete', $class);

        if ($class->enrollments()->exists()) {
            return back()->withErrors(['class' => __('This class has enrollment history and cannot be deleted. Mark it inactive instead.')]);
        }

        $class->delete();

        return redirect()->route('branch.academics.classes.index')->with('success', __('Class deleted.'));
    }

    protected function data(SchoolClassRequest $request): array
    {
        return array_merge($request->validated(), [
            'code' => strtoupper($request->validated('code')),
            'has_groups' => $request->boolean('has_groups'),
            'is_active' => $request->boolean('is_active'),
        ]);
    }
}
