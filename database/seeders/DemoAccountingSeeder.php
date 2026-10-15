<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Finance\Budget;
use App\Models\Finance\EmployeeSalary;
use App\Models\Finance\Expense;
use App\Models\Finance\Vendor;
use App\Models\HR\Employee;
use App\Models\Institution;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\PayrollRun;

/**
 * Demo expenses and payroll for every demo branch (local/staging only): opening cash & bank balances, suppliers,
 * salary structures for all active employees, last month's salary sheet (approved and paid), a handful of approved
 * expenses, one waiting for approval, one bought on credit, and a budget for the current financial year.
 * Everything goes through the same services as the UI, so the books stay balanced.
 * Idempotent and resumable: every step checks what already exists, so a run that stopped half-way is completed.
 */
class DemoAccountingSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SSC')->first();
        if (! $institution) {
            $this->command?->warn('Demo institution not found — run DemoInstitutionSeeder first.');

            return;
        }

        foreach (Branch::query()->where('institution_id', $institution->id)->with('institution')->ordered()->get() as $branch) {
            tenant()->runForBranch($branch, function () use ($branch) {
                $accountant = User::query()->where('email', "accounts.branch{$branch->sort_order}@demo.test")->first();
                $admin = User::query()->where('email', "admin.branch{$branch->sort_order}@demo.test")->first();
                $employees = Employee::query()->active()->get();
                if (! $accountant || ! $admin || $employees->isEmpty()) {
                    return;
                }
                mt_srand($branch->id * 3571);
                DB::transaction(fn () => $this->seedBranch($accountant, $admin, $employees));
            });
        }
    }

    protected function seedBranch(User $accountant, User $admin, $employees): void
    {
        $chart = app(ChartOfAccounts::class);
        $chart->ensure();
        $ledger = app(LedgerService::class);
        $today = CarbonImmutable::today();
        $year = $ledger->yearOf($today);
        if ($year->is_closed) {
            return;
        }
        $fyStart = CarbonImmutable::parse($year->starts_on);

        // Opening balances at the start of the financial year.
        if (! JournalEntry::query()->where('narration', 'Opening cash and bank balances')->exists()) {
            $ledger->post('journal', $fyStart, 'Opening cash and bank balances', [
                ['account_id' => $chart->id('cash'), 'debit' => 150000],
                ['account_id' => $chart->id('bank'), 'debit' => 1200000],
                ['account_id' => $chart->id('opening_balance'), 'credit' => 1350000],
            ], null, $accountant);
        }

        $vendors = Vendor::query()->exists() ? Vendor::query()->orderBy('id')->get()->values() : collect([
            ['name' => 'Desh Stationers', 'phone' => '+8801711223344', 'address' => 'Nilkhet, Dhaka'],
            ['name' => 'Bijli Electric & Hardware', 'phone' => '+8801811556677', 'address' => 'Station Road'],
            ['name' => 'Rupali Decorators', 'phone' => '+8801911889900', 'address' => 'College Road'],
        ])->map(fn ($v) => Vendor::query()->create($v + ['is_active' => true]));

        // Salary structures: basic from the HR record; house rent 40 %, medical, conveyance; provident fund 5 %.
        foreach ($employees as $employee) {
            if (EmployeeSalary::query()->where('employee_id', $employee->id)->exists()) {
                continue;
            }
            $basic = (float) ($employee->basic_salary ?: ($employee->employee_type === 'teacher' ? 25000 : 12000));
            EmployeeSalary::query()->create([
                'employee_id' => $employee->id, 'basic' => $basic,
                'allowances' => [
                    ['name' => 'House rent', 'amount' => round($basic * 0.4, 2)],
                    ['name' => 'Medical', 'amount' => 1500],
                    ['name' => 'Conveyance', 'amount' => 1000],
                ],
                'deductions' => [['name' => 'Provident fund', 'amount' => round($basic * 0.05, 2)]],
                'payment_method' => $employee->employee_type === 'teacher' ? 'bank' : 'cash',
                'account_number' => $employee->employee_type === 'teacher' ? '0200'.mt_rand(10000000, 99999999) : null,
                'effective_from' => $fyStart->toDateString(),
            ]);
        }

        // Last month's salary sheet: prepared by the accountant, approved by the branch admin, paid from the bank.
        $lastMonth = $today->subMonthNoOverflow()->startOfMonth();
        if ($lastMonth->gte($fyStart)) {
            $payroll = app(PayrollService::class);
            $run = PayrollRun::query()->where('period', $lastMonth->format('Y-m'))->where('status', '!=', 'cancelled')->first()
                ?? $payroll->prepare($lastMonth->format('Y-m'), $accountant);
            if ($run->status === 'draft') {
                $run = $payroll->approve($run, $admin);
            }
            if ($run->status === 'approved') {
                $payroll->pay($run->fresh(), $chart->get('bank'), $today->day >= 5 ? $today->setDay(5) : $today, $accountant);
            }
        }

        // Expenses of the last two months.
        $expenses = app(ExpenseService::class);
        $rows = Expense::query()->exists() ? [] : [
            [40, 'utilities', 'Electricity bill', mt_rand(8, 15) * 1000, 'paid', 'bank', 1],
            [35, 'stationery', 'Exam answer scripts and printing', mt_rand(5, 12) * 1000, 'paid', 'cash', 0],
            [28, 'maintenance', 'Ceiling fans and wiring repair', mt_rand(6, 20) * 1000, 'paid', 'cash', 1],
            [20, 'utilities', 'Internet and water bill', mt_rand(3, 6) * 1000, 'paid', 'bank', null],
            [12, 'events', 'Annual sports day decoration', mt_rand(15, 30) * 1000, 'credit', null, 2],
            [6, 'travel', 'Board office visit — conveyance', mt_rand(1, 3) * 1000, 'paid', 'cash', null],
            [2, 'stationery', 'Chalk, markers and registers', mt_rand(2, 5) * 1000, 'paid', 'cash', 0],
        ];
        foreach ($rows as $i => [$daysAgo, $key, $text, $amount, $mode, $from, $vendor]) {
            $date = $today->subDays($daysAgo);
            if ($date->lt($fyStart)) {
                $date = $fyStart;
            }
            $expense = $expenses->create([
                'date' => $date->toDateString(), 'account_id' => $chart->id($key), 'vendor_id' => $vendor !== null ? $vendors[$vendor]->id : null,
                'description' => $text, 'amount' => $amount, 'payment_mode' => $mode, 'paid_from_account_id' => $from ? $chart->id($from) : null,
                'reference' => 'BILL-'.mt_rand(1000, 9999),
            ], null, $accountant);
            if ($i < count($rows) - 1) {   // the newest one waits for approval
                $expenses->approve($expense, $admin, 'Checked with the bill.');
            }
        }

        // Budget for the current financial year.
        foreach (['fee_income' => 6000000, 'salaries' => 3600000, 'utilities' => 240000, 'stationery' => 120000, 'maintenance' => 150000, 'events' => 100000, 'travel' => 30000] as $key => $amount) {
            Budget::query()->firstOrCreate(['financial_year_id' => $year->id, 'account_id' => $chart->id($key)], ['amount' => $amount]);
        }
    }
}
