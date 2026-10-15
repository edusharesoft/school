<?php

namespace App\Http\Controllers\Branch\Operations;

use App\Http\Controllers\Controller;
use App\Models\Students\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Student picker used by the transport and hostel allocation screens (active students only). */
class StudentLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('transport.view') || $request->user()->can('hostel.view'), 403);
        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(Student::query()->search($term)->where('students.status', 'active')->with('currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name')
            ->orderBy('name')->limit(15)->get()->map(fn (Student $s) => [
                'id' => $s->id, 'name' => $s->name, 'code' => $s->student_code, 'gender' => $s->gender,
                'detail' => trim(($s->currentEnrollment?->schoolClass?->name ?? '').' '.($s->currentEnrollment?->section?->name ?? '')) ?: null,
            ])->values());
    }
}
