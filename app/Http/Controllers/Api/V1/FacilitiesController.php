<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Students\Student;
use App\Services\Operations\StudentFacilities;
use App\Services\Portal\PortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/my/facilities — school transport (route, stop, timings, driver, tracking link) and hostel (room, bed, warden, recent roll call) of the student, or of each child for a guardian. */
class FacilitiesController extends Controller
{
    use ApiResponse;

    public function mine(Request $request, PortalService $portal, StudentFacilities $facilities): JsonResponse
    {
        $user = $request->user();
        $students = collect();
        if ($student = $portal->studentFor($user)) {
            $students->push($student);
        } elseif ($guardian = $portal->guardianFor($user)) {
            $students = $portal->children($guardian);
        }
        abort_if($students->isEmpty(), 404);

        return $this->ok($students->map(fn (Student $s) => ['student' => ['id' => $s->id, 'name' => $s->name, 'code' => $s->student_code]] + $facilities->for($s))->values());
    }
}
