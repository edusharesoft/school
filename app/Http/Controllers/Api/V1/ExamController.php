<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Services\Portal\PortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/exams                        exams of the branch (staff with examinations.view)
 * GET /api/v1/exams/{id}/results?class_id=&section_id=   processed results (staff with results.view)
 * GET /api/v1/my/results                   published results of the signed-in student, or of a parent's children
 */
class ExamController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $this->authorize('examinations.view');

        return $this->ok(Examination::query()->with('session:id,name')->withCount('subjects')->latest('starts_on')->limit(100)->get()
            ->map(fn (Examination $e) => [
                'id' => $e->id, 'name' => $e->name, 'type' => $e->type, 'session' => $e->session?->name, 'status' => $e->status,
                'starts_on' => $e->starts_on?->toDateString(), 'ends_on' => $e->ends_on?->toDateString(), 'papers' => $e->subjects_count,
                'published_at' => $e->published_at?->toIso8601String(),
            ]));
    }

    public function results(Request $request, Examination $exam): JsonResponse
    {
        $this->authorize('results.view');

        $results = $exam->results()->with(['student:id,name,student_code', 'schoolClass:id,name', 'section:id,name'])
            ->when($request->integer('class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($request->integer('section_id'), fn ($q, $id) => $q->where('section_id', $id))
            ->orderBy('school_class_id')->orderByRaw('class_position IS NULL')->orderBy('class_position')->get();

        return $this->ok($results->map(fn (ExamResult $r) => $this->row($r) + ['student' => ['id' => $r->student_id, 'name' => $r->student?->name, 'code' => $r->student?->student_code]]),
            null, 200, ['exam_status' => $exam->status]);
    }

    public function mine(Request $request, PortalService $portal): JsonResponse
    {
        $user = $request->user();
        $students = collect();
        if ($student = $portal->studentFor($user)) {
            $students->push($student);
        } elseif ($guardian = $portal->guardianFor($user)) {
            $students = $portal->children($guardian);
        }
        abort_if($students->isEmpty(), 404);

        return $this->ok($students->map(fn ($s) => [
            'student' => ['id' => $s->id, 'name' => $s->name, 'code' => $s->student_code],
            'results' => $portal->publishedResults($s)->map(fn (ExamResult $r) => $this->row($r) + ['exam' => $r->examination?->name, 'subjects' => collect($r->subjects)->map(fn ($x) => [
                'subject' => $x['subject'], 'full' => $x['full'], 'total' => $x['total'], 'grade' => $x['grade'], 'point' => $x['point'], 'status' => $x['status'],
            ])->values()])->values(),
        ])->values());
    }

    protected function row(ExamResult $r): array
    {
        return [
            'id' => $r->id, 'examination_id' => $r->examination_id, 'class' => $r->schoolClass?->name, 'section' => $r->section?->name, 'roll_no' => $r->roll_no,
            'total_marks' => $r->total_marks, 'full_marks' => $r->full_marks, 'percentage' => $r->percentage, 'gpa' => $r->gpa, 'grade' => $r->grade,
            'status' => $r->status, 'class_position' => $r->class_position, 'section_position' => $r->section_position,
        ];
    }
}
