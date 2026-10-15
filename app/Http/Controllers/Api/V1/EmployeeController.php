<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\HR\Employee;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/teachers and /api/v1/employees (branch host). */
class EmployeeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()->with(['designation:id,name', 'department:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->route()?->getName() === 'api.v1.teachers.index', fn ($q) => $q->teachers())
            ->when($request->filled('type'), fn ($q) => $q->where('employee_type', $request->string('type')->toString()))
            ->where('status', $request->string('status')->toString() ?: 'active')
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->ok(EmployeeResource::collection($employees));
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->ok(new EmployeeResource($employee->load(['designation:id,name', 'department:id,name'])));
    }
}
