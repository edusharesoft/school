<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\HR\Employee;
use App\Models\HR\StaffBranchAssignment;
use App\Models\Scopes\BranchScope;
use App\Services\HR\StaffAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Central: teachers / staff of one branch working in another branch of the institution. */
class StaffAssignmentController extends Controller
{
    public function __construct(protected StaffAssignmentService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('employees.view');
        $institutionId = tenant()->institutionId();

        $assignments = StaffBranchAssignment::query()
            ->with(['employee' => fn ($q) => $q->with('branch:id,name,code'), 'branch:id,name,code', 'assigner:id,name'])
            ->when($request->query('show') !== 'all', fn ($q) => $q->current())
            ->latest('starts_on')->paginate(30)->withQueryString();

        $employees = Employee::query()->withoutGlobalScope(BranchScope::class)->with('branch:id,code')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'employee_code', 'branch_id']);

        return view('central.staff-assignments.index', [
            'assignments' => $assignments,
            'employees' => $employees,
            'branches' => Branch::query()->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))->active()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('employees.update');

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'duty' => ['nullable', 'string', 'max:191'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $this->service->assign((int) $data['employee_id'], (int) $data['branch_id'], $data['duty'] ?? null, $data['starts_on'], $data['ends_on'] ?? null, $request->user());

        return back()->with('success', __('Assignment saved. The employee’s login can now open that branch.'));
    }

    public function end(Request $request, StaffBranchAssignment $assignment): RedirectResponse
    {
        $this->authorize('employees.update');
        $this->service->end($assignment, $request->user());

        return back()->with('success', __('Assignment ended.'));
    }
}
