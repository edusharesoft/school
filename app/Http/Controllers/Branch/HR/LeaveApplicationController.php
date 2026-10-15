<?php

namespace App\Http\Controllers\Branch\HR;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\LeaveApplicationRequest;
use App\Models\HR\Employee;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Services\HR\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Leave applications (master prompt §10). Shared by the back-office (HR sees the branch) and the
 * teacher portal (a teacher sees and applies only for themself). The view picks the matching layout.
 */
class LeaveApplicationController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected LeaveService $leaves) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LeaveApplication::class);

        $user = $request->user();
        $all = $user->can('viewAll', LeaveApplication::class);
        $own = Employee::query()->where('user_id', $user->id)->first();
        $status = $request->string('status')->toString() ?: ($all ? 'pending' : 'all');

        $applications = LeaveApplication::query()
            ->with(['employee:id,name,employee_code,user_id', 'leaveType:id,name', 'reviewer:id,name'])
            ->when(! $all, fn ($q) => $q->where('employee_id', $own?->id ?? 0))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($all && $request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->latest('from_date')
            ->paginate(25)->withQueryString();

        return view('branch.hr.leaves.index', [
            'applications' => $applications,
            'status' => $status,
            'all' => $all,
            'own' => $own,
            'balances' => $own ? $this->leaves->balances($own, (int) now()->format('Y')) : collect(),
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LeaveApplication::class);

        $own = Employee::query()->where('user_id', $request->user()->id)->first();
        $forOthers = $request->user()->can('createForOthers', LeaveApplication::class) && $this->routePrefix($request) === 'branch.';

        abort_if(! $own && ! $forOthers, 403, __('Your login is not linked to an employee record. Ask HR to link it.'));

        return view('branch.hr.leaves.create', [
            'own' => $own,
            'employees' => $forOthers ? Employee::query()->active()->orderBy('name')->get(['id', 'name', 'employee_code']) : collect(),
            'types' => LeaveType::query()->active()->orderBy('name')->get(),
            'balances' => $own ? $this->leaves->balances($own, (int) now()->format('Y')) : collect(),
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(LeaveApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $employeeId = $request->validated('employee_id');

        if ($employeeId && $this->routePrefix($request) === 'branch.' && $user->can('createForOthers', LeaveApplication::class)) {
            $employee = Employee::query()->findOrFail($employeeId);
        } else {
            $employee = Employee::query()->where('user_id', $user->id)->first();
            abort_unless($employee, 403, __('Your login is not linked to an employee record. Ask HR to link it.'));
        }

        $this->leaves->apply(
            $employee,
            LeaveType::query()->findOrFail($request->validated('leave_type_id')),
            $request->validated('from_date'),
            $request->validated('to_date'),
            $request->boolean('is_half_day'),
            $request->validated('reason'),
            $user,
        );

        return redirect()->route($this->routePrefix($request).'leaves.index')->with('success', __('Leave application submitted.'));
    }

    public function approve(Request $request, LeaveApplication $leave): RedirectResponse
    {
        $this->authorize('review', $leave);
        $this->leaves->approve($leave, $request->user(), $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null);

        return back()->with('success', __('Leave approved and applied to attendance.'));
    }

    public function reject(Request $request, LeaveApplication $leave): RedirectResponse
    {
        $this->authorize('review', $leave);
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $this->leaves->reject($leave, $request->user(), $data['note']);

        return back()->with('success', __('Leave application rejected.'));
    }

    public function cancel(Request $request, LeaveApplication $leave): RedirectResponse
    {
        $this->authorize('cancel', $leave);
        $this->leaves->cancel($leave, $request->user());

        return back()->with('success', __('Leave application cancelled.'));
    }
}
