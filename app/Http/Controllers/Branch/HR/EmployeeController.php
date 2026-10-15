<?php

namespace App\Http\Controllers\Branch\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\EmployeeRequest;
use App\Models\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use App\Services\HR\EmployeeService;
use App\Services\HR\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Teacher & employee profiles (master prompt §10). */
class EmployeeController extends Controller
{
    public function __construct(protected EmployeeService $employees) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $status = $request->string('status')->toString() ?: 'active';

        $employees = Employee::query()
            ->with(['designation:id,name', 'department:id,name', 'user:id,status'])
            ->search($request->string('q')->toString())
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('type'), fn ($q) => $q->where('employee_type', $request->string('type')->toString()))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->integer('designation_id'), fn ($q, $id) => $q->where('designation_id', $id))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('branch.hr.employees.index', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'),
            'designations' => Designation::query()->ordered()->pluck('name', 'id'),
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Employee::class);

        return view('branch.hr.employees.create', $this->formOptions($request) + [
            'employee' => new Employee(['employee_type' => 'teacher', 'employment_type' => 'permanent', 'joining_date' => now()]),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        abort_if($request->accountData() !== null && ! $request->user()->can('users.create'), 403);

        $employee = $this->employees->create($request->employeeData(), $request->accountData(), $request->user());
        $this->storePhoto($request, $employee);

        return redirect()->route('branch.hr.employees.show', $employee)
            ->with('success', __('Employee added. ID: :code', ['code' => $employee->employee_code]));
    }

    public function show(Request $request, Employee $employee, LeaveService $leaves): View
    {
        $this->authorize('view', $employee);

        $employee->load(['designation', 'department', 'user.roles', 'documents.uploader:id,name', 'histories.recorder:id,name',
            'leaveApplications' => fn ($q) => $q->with('leaveType:id,name')->limit(10)]);

        $month = now()->startOfMonth();
        $attendance = $employee->attendances()->whereDate('date', '>=', $month->toDateString())
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return view('branch.hr.employees.show', [
            'employee' => $employee,
            'canSeeConfidential' => $request->user()->can('viewConfidential', $employee),
            'documentTypes' => EmployeeDocument::TYPES,
            'balances' => $leaves->balances($employee, (int) now()->format('Y')),
            'attendance' => $attendance,
            'roles' => $request->user()->can('createAccount', $employee) && ! $employee->user_id
                ? $this->employees->assignableRoles($request->user())->pluck('label', 'name')->map(fn ($l, $n) => $l ?: $n)
                : collect(),
        ]);
    }

    public function edit(Request $request, Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('branch.hr.employees.edit', $this->formOptions($request) + [
            'employee' => $employee,
            'canSeeConfidential' => $request->user()->can('viewConfidential', $employee),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employees->update($employee, $request->employeeData(), $request->user(), $request->user()->can('viewConfidential', $employee));
        $this->storePhoto($request, $employee);

        return redirect()->route('branch.hr.employees.show', $employee)->with('success', __('Employee profile updated.'));
    }

    public function changeStatus(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('changeStatus', $employee);

        $data = $request->validate([
            'status' => ['required', Rule::in(Employee::STATUSES)],
            'date' => ['required', 'date', 'before_or_equal:+30 days'],
            'reason' => ['nullable', 'required_if:status,resigned,terminated,retired', 'string', 'max:500'],
        ]);

        $this->employees->changeStatus($employee, $data['status'], $data['date'], $data['reason'] ?? null, $request->user());

        return back()->with('success', __('Employee status changed to :status.', ['status' => __(ucfirst($data['status']))]));
    }

    /** Give an existing employee a login (teacher portal / back-office). */
    public function createAccount(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('createAccount', $employee);

        $data = $request->validate([
            'account_email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'account_password' => ['required', Password::min(8)->letters()->numbers()],
            'account_role' => ['required', 'string'],
        ]);

        $this->employees->createAccount($employee, [
            'email' => $data['account_email'], 'password' => $data['account_password'], 'role' => $data['account_role'],
        ], $request->user());

        return back()->with('success', __('Login account created for :name.', ['name' => $employee->name]));
    }

    /** Photos live on the private disk; served only after authorization. */
    public function photo(Employee $employee): StreamedResponse
    {
        $this->authorize('view', $employee);
        abort_unless($employee->photo_path && Storage::disk('local')->exists($employee->photo_path), 404);

        return Storage::disk('local')->response($employee->photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    protected function formOptions(Request $request): array
    {
        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'designations' => Designation::query()->active()->ordered()->get(['id', 'name', 'category']),
            'roles' => $this->employees->assignableRoles($request->user())->pluck('label', 'name')->map(fn ($l, $n) => $l ?: $n),
        ];
    }

    protected function storePhoto(EmployeeRequest $request, Employee $employee): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $old = $employee->photo_path;
        $path = $request->file('photo')->store(tenant()->branch()->storagePath('employees/'.$employee->id), 'local');
        $employee->forceFill(['photo_path' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
    }
}
