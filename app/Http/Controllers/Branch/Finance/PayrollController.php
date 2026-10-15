<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\EmployeeSalary;
use App\Models\Finance\PayrollItem;
use App\Models\Finance\PayrollRun;
use App\Models\HR\Employee;
use App\Services\Finance\LedgerService;
use App\Services\Finance\PayrollService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Salary structures, monthly salary sheets (prepare → approve → pay), salary slips. */
class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    public function index(): View
    {
        $this->authorize('payroll.view');

        return view('branch.finance.payroll.index', [
            'runs' => PayrollRun::query()->with(['preparer:id,name', 'approver:id,name'])->latest('period')->latest('id')->paginate(24),
            'period' => now()->format('Y-m'),
            'withoutStructure' => Employee::query()->active()->whereDoesntHave('salary')->count(),
        ]);
    }

    public function salaries(Request $request): View
    {
        $this->authorize('payroll.view');
        abort_unless($request->user()->can('employees.view-salary') || $request->user()->can('payroll.process'), 403);

        return view('branch.finance.payroll.salaries', [
            'employees' => Employee::query()->with(['salary', 'designation:id,name'])->active()->orderBy('employee_code')->get(),
            'editing' => $request->integer('employee_id') ? Employee::query()->with('salary')->find($request->integer('employee_id')) : null,
        ]);
    }

    public function saveSalary(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('payroll.process');
        $data = $request->validate([
            'basic' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'allowances' => ['nullable', 'array', 'max:10'],
            'allowances.*.name' => ['nullable', 'string', 'max:60'],
            'allowances.*.amount' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'array', 'max:10'],
            'deductions.*.name' => ['nullable', 'string', 'max:60'],
            'deductions.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['bank', 'cash', 'mobile'])],
            'account_number' => ['nullable', 'string', 'max:40'],
            'effective_from' => ['required', 'date'],
        ]);
        $rows = fn ($list) => array_values(array_filter(array_map(fn ($r) => ['name' => trim((string) ($r['name'] ?? '')), 'amount' => round((float) ($r['amount'] ?? 0), 2)], $list ?? []),
            fn ($r) => $r['name'] !== '' && $r['amount'] > 0));

        EmployeeSalary::query()->updateOrCreate(['employee_id' => $employee->id], [
            'basic' => round((float) $data['basic'], 2), 'allowances' => $rows($data['allowances'] ?? []), 'deductions' => $rows($data['deductions'] ?? []),
            'payment_method' => $data['payment_method'], 'account_number' => $data['account_number'] ?? null, 'effective_from' => $data['effective_from'],
        ]);

        return redirect()->route('branch.finance.payroll.salaries')->with('success', __('Salary structure of :name saved.', ['name' => $employee->name]));
    }

    public function prepare(Request $request): RedirectResponse
    {
        $this->authorize('payroll.process');
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $run = $this->payroll->prepare($data['period'], $request->user());

        return redirect()->route('branch.finance.payroll.show', $run)->with('success', __('Salary sheet prepared for :count employee(s).', ['count' => $run->employees]));
    }

    public function show(PayrollRun $run): View
    {
        $this->authorize('payroll.view');
        $run->load(['items.employee.designation:id,name', 'items.employee.department:id,name', 'preparer:id,name', 'approver:id,name', 'paidFrom']);

        return view('branch.finance.payroll.show', [
            'run' => $run,
            'moneyAccounts' => Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
            'entries' => app(LedgerService::class)->entriesFor($run),
        ]);
    }

    public function adjust(Request $request, PayrollRun $run, PayrollItem $item): RedirectResponse
    {
        $this->authorize('payroll.process');
        abort_unless($item->payroll_run_id === $run->id, 404);
        $data = $request->validate(['bonus' => ['nullable', 'numeric', 'min:0'], 'deduction' => ['nullable', 'numeric', 'min:0'], 'remarks' => ['nullable', 'string', 'max:255']]);
        $this->payroll->adjust($item, (float) ($data['bonus'] ?? 0), (float) ($data['deduction'] ?? 0), $data['remarks'] ?? null);

        return back()->with('success', __('Updated.'));
    }

    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->authorize('payroll.approve');
        $this->payroll->approve($run, $request->user());

        return back()->with('success', __('Salary sheet approved and posted.'));
    }

    public function pay(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->authorize('payroll.process');
        $data = $request->validate(['account_id' => ['required', 'integer', TenantRule::exists('accounts')], 'date' => ['required', 'date', 'before_or_equal:today']]);
        $this->payroll->pay($run, Account::query()->findOrFail($data['account_id']), $data['date'], $request->user());

        return back()->with('success', __('Salary payment recorded.'));
    }

    public function cancel(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->authorize($run->status === 'draft' ? 'payroll.process' : 'payroll.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->payroll->cancel($run, $data['reason'], $request->user());

        return redirect()->route('branch.finance.payroll.index')->with('success', __('Salary sheet cancelled.'));
    }

    /** Salary slips: every employee of the run, or one (?item=). */
    public function slips(Request $request, PayrollRun $run): View
    {
        $this->authorize('payroll.view');
        $items = $run->items()->with(['employee.designation:id,name', 'employee.department:id,name', 'employee.salary'])
            ->when($request->integer('item'), fn ($q, $id) => $q->whereKey($id))->get();
        abort_if($items->isEmpty(), 404);

        return view('branch.finance.payroll.slips', ['run' => $run, 'items' => $items, 'branch' => tenant()->branch()]);
    }
}
