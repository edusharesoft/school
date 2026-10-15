<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Students\Student;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/students?q=&status=&class_id=&section_id=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Student::class);

        $students = Student::query()
            ->search($request->string('q')->toString())
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('class_id') || $request->integer('section_id'), fn ($q) => $q->whereHas('currentEnrollment', fn ($e) => $e
                ->when($request->integer('class_id'), fn ($e, $id) => $e->where('school_class_id', $id))
                ->when($request->integer('section_id'), fn ($e, $id) => $e->where('section_id', $id))))
            ->with(['currentEnrollment.session:id,name', 'currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name', 'currentEnrollment.studyGroup:id,name'])
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->ok(StudentResource::collection($students));
    }

    public function show(Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $student->load(['guardians', 'currentEnrollment.session:id,name', 'currentEnrollment.schoolClass:id,name',
            'currentEnrollment.section:id,name', 'currentEnrollment.studyGroup:id,name']);

        return $this->ok(new StudentResource($student));
    }
}
