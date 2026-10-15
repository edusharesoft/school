<?php

namespace App\Http\Controllers\Branch\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\PromotionRequest;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Services\Students\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Session-end promotion: pick a source class/section, tick students, choose the target session & class. */
class PromotionController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('promote', Student::class);

        $sessions = AcademicSession::query()->latest('starts_on')->get(['id', 'name', 'is_current', 'status']);
        $classes = SchoolClass::query()->ordered()->with('sections:id,school_class_id,name')->get();

        $sourceSession = $request->integer('session_id');
        $sourceClass = $request->integer('class_id');

        $enrollments = $sourceSession && $sourceClass
            ? StudentEnrollment::query()->with(['student:id,name,student_code', 'section:id,name'])
                ->where('academic_session_id', $sourceSession)->where('school_class_id', $sourceClass)->where('status', 'enrolled')
                ->when($request->integer('section_id'), fn ($q, $id) => $q->where('section_id', $id))
                ->orderBy('section_id')->orderBy('roll_no')->get()
            : collect();

        $suggested = $sourceClass ? $classes->firstWhere('id', $sourceClass)?->nextClass() : null;

        return view('branch.students.promotion', compact('sessions', 'classes', 'enrollments', 'suggested'));
    }

    public function store(PromotionRequest $request, StudentService $students): RedirectResponse
    {
        $result = $students->promote(
            $request->validated('enrollment_ids'),
            (int) $request->validated('target_session_id'),
            (int) $request->validated('target_class_id'),
            $request->validated('target_section_id') ? (int) $request->validated('target_section_id') : null,
            $request->validated('outcome'),
        );

        $message = __(':n students moved.', ['n' => $result['promoted']]);
        if ($result['skipped']) {
            $message .= ' '.__(':n skipped.', ['n' => count($result['skipped'])]);
        }

        return back()->with($result['skipped'] ? 'warning' : 'success', $message)->with('promotion_skipped', $result['skipped']);
    }
}
