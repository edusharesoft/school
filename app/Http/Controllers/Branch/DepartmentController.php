<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reference CRUD for a tenant-owned module. Route-model binding goes through BranchScope,
 * so /departments/{id} of another branch is a 404; policies add the permission check.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        $sort = in_array($request->query('sort'), ['name', 'code', 'created_at'], true) ? $request->query('sort') : 'name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $departments = Department::query()
            ->search($request->string('q')->toString())
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        return view('branch.departments.index', compact('departments', 'sort', 'dir'));
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('branch.departments.form', ['department' => new Department(['is_active' => true])]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('branch.departments.index')->with('success', __('Department created.'));
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('branch.departments.form', compact('department'));
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('branch.departments.index')->with('success', __('Department updated.'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        $department->delete();

        return redirect()->route('branch.departments.index')->with('success', __('Department archived.'));
    }
}
