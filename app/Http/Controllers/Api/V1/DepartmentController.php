<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\DepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->search($request->string('q')->toString())
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->ok(DepartmentResource::collection($departments));
    }

    public function store(DepartmentRequest $request): JsonResponse
    {
        $department = Department::query()->create($request->validated());

        return $this->ok(new DepartmentResource($department), __('Department created.'), 201);
    }

    public function show(Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        return $this->ok(new DepartmentResource($department));
    }

    public function update(DepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());

        return $this->ok(new DepartmentResource($department), __('Department updated.'));
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);
        $department->delete();

        return $this->ok(null, __('Department archived.'));
    }
}
