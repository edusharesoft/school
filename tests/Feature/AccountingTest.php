<?php

namespace Tests\Feature;

use App\Models\Attendance\StaffAttendance;
use App\Models\Branch;
use App\Models\Finance\Budget;
use App\Models\Finance\EmployeeSalary;
use App\Models\Finance\Expense;
use App\Models\Finance\FinancialYear;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use App\Models\Finance\PayrollItem;
use App\Models\Finance\PayrollRun;
use App\Models\Finance\Vendor;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\Finance\AccountingReportService;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0)); // financial year 2025-26 (July – June)
    }

    /** Chart of accounts + opening cash of 50,000 posted through the manual voucher screen. */
    protected function books(Branch $branch, User $accountant): array
    {
        $ids = $this->inBranch($branch, function () {
            $chart = app(ChartOfAccounts::class);
            $chart->ensure();

            return collect(['cash', 'bank', 'opening_balance', 'utilities', 'stationery', 'payable', 'salaries', 'salary_payable', 'salary_deductions', 'fee_income'])
                ->mapWithKeys(fn ($k) => [$k => $chart->id($k)])->all();
        });

        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'), [
            'type' => 'journal', 'date' => '2025-07-01', 'narration' => 'Opening cash',
            'lines' => [
                ['account_id' => $ids['cash'], 'debit' => 50000],
                ['account_id' => $ids['opening_balance'], 'credit' => 50000],
            ],
        ])->assertSessionHasNoErrors();

        return $ids;
    }

    protected function balance(Branch $branch, string $key): float
    {
        return $this->inBranch($branch, fn () => app(LedgerService::class)->balance(app(ChartOfAccounts::class)->get($key)));
    }

    protected function assertBooksBalance(Branch $branch): void
    {
        $this->inBranch($branch, function () {
            $this->assertEqualsWithDelta((float) JournalLine::query()->sum('debit'), (float) JournalLine::query()->sum('credit'), 0.001);
            foreach (JournalEntry::query()->with('lines')->get() as $entry) {
                $this->assertEqualsWithDelta($entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.001, "Entry {$entry->entry_no} balances");
            }
        });
    }

    public function test_expense_request_approval_payment_and_reversal_keep_the_books_balanced(): void
    {
        Storage::fake('local');
        $branch = $this->makeBranch();
        $accountant = $this->branchUser($branch, 'accountant');
        $admin = $this->branchUser($branch, 'branch-admin');
        $ids = $this->books($branch, $accountant);

        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/vendors'), ['name' => 'Desh Stationers', 'phone' => '01711000000'])
            ->assertSessionHasNoErrors();
        $vendor = $this->inBranch($branch, fn () => Vendor::query()->firstOrFail());

        // Paid in cash: posted on approval.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/expenses'), [
            'date' => '2026-03-10', 'account_id' => $ids['utilities'], 'description' => 'Electricity bill February', 'amount' => 4200,
            'payment_mode' => 'paid', 'paid_from_account_id' => $ids['cash'], 'reference' => 'DESCO-221',
            'attachment' => UploadedFile::fake()->create('bill.pdf', 30, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $bill = $this->inBranch($branch, fn () => Expense::query()->where('description', 'Electricity bill February')->firstOrFail());
        $this->assertSame('pending', $bill->status);
        $this->assertStringContainsString('-EXP26-', $bill->expense_no);
        $this->assertSame(0.0, $this->balance($branch, 'utilities'), 'Nothing is posted before approval');

        // Nobody approves their own request (accountant also has expenses.approve).
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/expenses/{$bill->id}/approve"))->assertSessionHasErrors('status');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/expenses/{$bill->id}/approve"), ['note' => 'OK'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertEqualsWithDelta(4200, $this->balance($branch, 'utilities'), 0.001);
        $this->assertEqualsWithDelta(45800, $this->balance($branch, 'cash'), 0.001);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "finance/expenses/{$bill->id}"))->assertOk()->assertSee($bill->expense_no);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "finance/expenses/{$bill->id}/attachment"))->assertOk();

        // Bought on credit: approval → accounts payable; payment clears it.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/expenses'), [
            'date' => '2026-03-12', 'account_id' => $ids['stationery'], 'vendor_id' => $vendor->id, 'description' => 'Exam papers', 'amount' => 3000,
            'payment_mode' => 'credit',
        ])->assertSessionHasNoErrors();
        $credit = $this->inBranch($branch, fn () => Expense::query()->where('description', 'Exam papers')->firstOrFail());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/expenses/{$credit->id}/approve"))->assertSessionHasNoErrors();
        $this->assertSame('approved', $credit->fresh()->status);
        $this->assertEqualsWithDelta(3000, $this->balance($branch, 'payable'), 0.001);
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/expenses/{$credit->id}/pay"), ['account_id' => $ids['cash'], 'date' => '2026-03-14'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $credit->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->balance($branch, 'payable'), 0.001);
        $this->assertEqualsWithDelta(42800, $this->balance($branch, 'cash'), 0.001);

        // A rejected request never touches the books.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/expenses'), [
            'date' => '2026-03-13', 'account_id' => $ids['utilities'], 'description' => 'Duplicate bill', 'amount' => 4200,
            'payment_mode' => 'paid', 'paid_from_account_id' => $ids['cash'],
        ])->assertSessionHasNoErrors();
        $dup = $this->inBranch($branch, fn () => Expense::query()->where('description', 'Duplicate bill')->firstOrFail());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/expenses/{$dup->id}/reject"), ['note' => 'Already paid'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $dup->fresh()->status);
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/expenses/{$dup->id}/approve"))->assertSessionHasErrors('status');

        // Reversal mirrors every posted entry.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/expenses/{$bill->id}/reverse"), ['reason' => 'Posted to the wrong month'])
            ->assertSessionHasNoErrors();
        $this->assertSame('reversed', $bill->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->balance($branch, 'utilities'), 0.001);
        $this->assertEqualsWithDelta(47000, $this->balance($branch, 'cash'), 0.001);
        $this->assertBooksBalance($branch);

        // Source-linked vouchers are reversed from their document, not from the day book.
        $entry = $this->inBranch($branch, fn () => app(LedgerService::class)->entriesFor($credit)->firstWhere('status', 'posted'));
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/journal/{$entry->id}/reverse"), ['reason' => 'Trying from day book'])
            ->assertSessionHasErrors('reason');

        foreach (['finance/expenses', 'finance/expenses/create', 'finance/vendors'] as $page) {
            $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, $page))->assertOk();
        }
    }

    public function test_payroll_deducts_unpaid_days_needs_a_second_approver_and_posts_salaries(): void
    {
        $branch = $this->makeBranch();
        $hr = $this->branchUser($branch, 'hr-manager');
        $admin = $this->branchUser($branch, 'branch-admin');
        $accountant = $this->branchUser($branch, 'accountant');
        $ids = $this->books($branch, $accountant);
        $rahima = $this->makeEmployee($branch, ['name' => 'Rahima Khatun']);
        $karim = $this->makeEmployee($branch, ['name' => 'Karim Mia', 'gender' => 'male']);

        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, "finance/payroll/salaries/{$rahima->id}"), [
            'basic' => 15000, 'payment_method' => 'bank', 'account_number' => '0123456789', 'effective_from' => '2025-07-01',
            'allowances' => [['name' => 'House rent', 'amount' => 5000], ['name' => '', 'amount' => '']],
            'deductions' => [['name' => 'Provident fund', 'amount' => 1500]],
        ])->assertSessionHasNoErrors();
        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, "finance/payroll/salaries/{$karim->id}"), [
            'basic' => 12000, 'payment_method' => 'cash', 'effective_from' => '2025-07-01',
        ])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(20000, $this->inBranch($branch, fn () => EmployeeSalary::query()->where('employee_id', $rahima->id)->firstOrFail()->gross()), 0.001);

        // February: Rahima absent 2 days; Karim 1 day of approved leave without pay, 1 day of paid leave.
        $this->inBranch($branch, function () use ($rahima, $karim) {
            $lwp = LeaveType::query()->create(['name' => 'Leave without pay', 'code' => 'LWP', 'days_per_year' => 0, 'is_paid' => false, 'is_active' => true]);
            $casual = LeaveType::query()->create(['name' => 'Casual leave', 'code' => 'CL', 'days_per_year' => 10, 'is_paid' => true, 'is_active' => true]);
            $unpaid = LeaveApplication::query()->create(['employee_id' => $karim->id, 'leave_type_id' => $lwp->id, 'from_date' => '2026-02-16', 'to_date' => '2026-02-16',
                'days' => 1, 'reason' => 'Family matter', 'status' => 'approved']);
            $paid = LeaveApplication::query()->create(['employee_id' => $karim->id, 'leave_type_id' => $casual->id, 'from_date' => '2026-02-17', 'to_date' => '2026-02-17',
                'days' => 1, 'reason' => 'Sick', 'status' => 'approved']);
            foreach ([[$rahima->id, '2026-02-10', 'absent', null], [$rahima->id, '2026-02-11', 'absent', null], [$rahima->id, '2026-02-12', 'present', null],
                [$karim->id, '2026-02-16', 'leave', $unpaid->id], [$karim->id, '2026-02-17', 'leave', $paid->id], [$karim->id, '2026-03-02', 'absent', null]] as [$emp, $date, $status, $leave]) {
                StaffAttendance::query()->create(['employee_id' => $emp, 'date' => $date, 'status' => $status, 'leave_application_id' => $leave, 'method' => 'manual']);
            }
        });

        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, 'finance/payroll'), ['period' => '2026-02'])->assertSessionHasNoErrors();
        $run = $this->inBranch($branch, fn () => PayrollRun::query()->where('period', '2026-02')->firstOrFail());
        $this->assertSame('draft', $run->status);
        $this->assertSame(2, $run->employees);
        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, 'finance/payroll'), ['period' => '2026-02'])->assertSessionHasErrors('period');

        [$r, $k] = $this->inBranch($branch, fn () => [PayrollItem::query()->where('employee_id', $rahima->id)->firstOrFail(), PayrollItem::query()->where('employee_id', $karim->id)->firstOrFail()]);
        $this->assertSame(2, $r->unpaid_days);
        $this->assertEqualsWithDelta(1000, $r->unpaid_deduction, 0.001);      // 15000 ÷ 30 × 2
        $this->assertEqualsWithDelta(17500, $r->net, 0.001);                  // 20000 − 1500 PF − 1000
        $this->assertSame(1, $k->unpaid_days, 'Paid leave and absences outside the month are not deducted');
        $this->assertEqualsWithDelta(11600, $k->net, 0.001);                  // 12000 − 400

        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, "finance/payroll/{$run->id}/items/{$k->id}"), ['bonus' => 1000, 'deduction' => 0, 'remarks' => 'Exam duty'])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(12600, $k->fresh()->net, 0.001);
        $this->assertEqualsWithDelta(30100, $run->fresh()->net, 0.001);

        // The preparer cannot approve; a teacher cannot even see payroll.
        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, "finance/payroll/{$run->id}/approve"))->assertSessionHasErrors('status');
        $this->actingOnBranch($this->branchUser($branch, 'teacher'), $branch)->get($this->branchUrl($branch, 'finance/payroll'))->assertForbidden();
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/payroll/{$run->id}/approve"))->assertSessionHasNoErrors();
        $this->assertSame('approved', $run->fresh()->status);
        $this->assertEqualsWithDelta(31600, $this->balance($branch, 'salaries'), 0.001);          // earned: 19000 + 12600
        $this->assertEqualsWithDelta(30100, $this->balance($branch, 'salary_payable'), 0.001);
        $this->assertEqualsWithDelta(1500, $this->balance($branch, 'salary_deductions'), 0.001);
        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, "finance/payroll/{$run->id}/items/{$k->id}"), ['bonus' => 5000, 'deduction' => 0])
            ->assertSessionHasErrors('status');

        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/payroll/{$run->id}/pay"), ['account_id' => $ids['cash'], 'date' => '2026-03-05'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $run->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->balance($branch, 'salary_payable'), 0.001);
        $this->assertEqualsWithDelta(50000 - 30100, $this->balance($branch, 'cash'), 0.001);

        foreach (['finance/payroll', 'finance/payroll/salaries', "finance/payroll/{$run->id}", "finance/payroll/{$run->id}/slips", "finance/payroll/{$run->id}/slips?item={$r->id}"] as $page) {
            $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, $page))->assertOk();
        }
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, "finance/payroll/{$run->id}/slips"))->assertSee('Rahima Khatun')->assertSee('17,500.00');

        // Cancelling a paid sheet needs payroll.approve and reverses everything.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/payroll/{$run->id}/cancel"), ['reason' => 'Wrong month'])->assertForbidden();
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/payroll/{$run->id}/cancel"), ['reason' => 'Wrong month'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $run->fresh()->status);
        foreach (['salaries', 'salary_payable', 'salary_deductions'] as $key) {
            $this->assertEqualsWithDelta(0, $this->balance($branch, $key), 0.001);
        }
        $this->assertEqualsWithDelta(50000, $this->balance($branch, 'cash'), 0.001);
        $this->assertBooksBalance($branch);

        // Cancelled months can be prepared again.
        $this->actingOnBranch($hr, $branch)->post($this->branchUrl($branch, 'finance/payroll'), ['period' => '2026-02'])->assertSessionHasNoErrors();
    }

    public function test_manual_vouchers_contra_rule_and_reversal(): void
    {
        $branch = $this->makeBranch();
        $accountant = $this->branchUser($branch, 'accountant');
        $admin = $this->branchUser($branch, 'branch-admin');
        $ids = $this->books($branch, $accountant);

        // Unbalanced voucher is refused.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'), [
            'type' => 'journal', 'date' => '2026-03-01', 'narration' => 'Broken',
            'lines' => [['account_id' => $ids['cash'], 'debit' => 100], ['account_id' => $ids['fee_income'], 'credit' => 90]],
        ])->assertSessionHasErrors('lines');
        // Contra only between money accounts.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'), [
            'type' => 'contra', 'date' => '2026-03-01', 'narration' => 'Wrong contra',
            'lines' => [['account_id' => $ids['cash'], 'debit' => 100], ['account_id' => $ids['fee_income'], 'credit' => 100]],
        ])->assertSessionHasErrors('lines');
        // Future dates are refused.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'), [
            'type' => 'journal', 'date' => '2026-04-01', 'narration' => 'Future',
            'lines' => [['account_id' => $ids['cash'], 'debit' => 100], ['account_id' => $ids['fee_income'], 'credit' => 100]],
        ])->assertSessionHasErrors('date');

        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'), [
            'type' => 'contra', 'date' => '2026-03-02', 'narration' => 'Cash deposited to bank',
            'lines' => [['account_id' => $ids['bank'], 'debit' => 20000], ['account_id' => $ids['cash'], 'credit' => 20000], ['account_id' => '', 'debit' => '']],
        ])->assertSessionHasNoErrors();
        $contra = $this->inBranch($branch, fn () => JournalEntry::query()->where('type', 'contra')->firstOrFail());
        $this->assertEqualsWithDelta(20000, $this->balance($branch, 'bank'), 0.001);

        // Admin (accounting.view only) can read but not post.
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'finance/journal'))->assertOk()->assertSee($contra->entry_no);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "finance/journal/{$contra->id}"))->assertOk();
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'finance/journal/create'))->assertForbidden();

        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/journal/{$contra->id}/reverse"), ['reason' => 'Deposit bounced'])
            ->assertSessionHasNoErrors();
        $this->assertSame('reversed', $contra->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->balance($branch, 'bank'), 0.001);
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/journal/{$contra->id}/reverse"), ['reason' => 'Second time'])
            ->assertSessionHasErrors('entry');
        $this->assertBooksBalance($branch);
    }

    public function test_statements_agree_with_each_other(): void
    {
        $branch = $this->makeBranch();
        $accountant = $this->branchUser($branch, 'accountant');
        $ids = $this->books($branch, $accountant);
        $post = fn (string $type, string $date, array $lines) => $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'),
            ['type' => $type, 'date' => $date, 'narration' => ucfirst($type).' '.$date, 'lines' => $lines])->assertSessionHasNoErrors();

        $post('receipt', '2026-02-05', [['account_id' => $ids['cash'], 'debit' => 30000], ['account_id' => $ids['fee_income'], 'credit' => 30000]]);
        $post('payment', '2026-02-20', [['account_id' => $ids['utilities'], 'debit' => 6000], ['account_id' => $ids['cash'], 'credit' => 6000]]);
        $post('contra', '2026-03-01', [['account_id' => $ids['bank'], 'debit' => 10000], ['account_id' => $ids['cash'], 'credit' => 10000]]);
        $post('journal', '2026-03-03', [['account_id' => $ids['stationery'], 'debit' => 2500], ['account_id' => $ids['payable'], 'credit' => 2500]]);

        $this->inBranch($branch, function () use ($ids) {
            $reports = app(AccountingReportService::class);
            $today = CarbonImmutable::today();

            $tb = $reports->trialBalance($today);
            $this->assertEqualsWithDelta($tb['debit'], $tb['credit'], 0.001);
            $this->assertEqualsWithDelta(50000 + 30000 + 2500, $tb['debit'], 0.001); // cash 64000 + bank 10000 + utilities 6000 + stationery 2500

            $ie = $reports->incomeExpenditure(CarbonImmutable::parse('2025-07-01'), $today);
            $this->assertEqualsWithDelta(30000, $ie['totalIncome'], 0.001);
            $this->assertEqualsWithDelta(8500, $ie['totalExpense'], 0.001);
            $this->assertEqualsWithDelta(21500, $ie['surplus'], 0.001);

            $bs = $reports->balanceSheet($today);
            $this->assertTrue($bs['balanced']);
            $this->assertEqualsWithDelta(21500, $bs['surplus'], 0.001);
            $this->assertEqualsWithDelta(74000, $bs['totalAssets'], 0.001);
            $this->assertEqualsWithDelta(2500, $bs['totalLiabilities'], 0.001);

            $cf = $reports->cashFlow(CarbonImmutable::parse('2026-02-01'), $today);
            $this->assertEqualsWithDelta(50000, $cf['opening'], 0.001);
            $this->assertEqualsWithDelta(30000, $cf['totalIn'], 0.001);
            $this->assertEqualsWithDelta(6000, $cf['totalOut'], 0.001, 'The cash → bank contra is not a flow');
            $this->assertEqualsWithDelta($cf['opening'] + $cf['totalIn'] - $cf['totalOut'], $cf['closing'], 0.01);

            $ledger = $reports->generalLedger(app(ChartOfAccounts::class)->get('cash'), CarbonImmutable::parse('2026-02-01'), $today);
            $this->assertEqualsWithDelta(50000, $ledger['opening'], 0.001);
            $this->assertEqualsWithDelta(64000, $ledger['closing'], 0.001);
            $this->assertCount(3, $ledger['rows']);
        });

        foreach (['finance/reports/ledger?account_id='.$ids['cash'], 'finance/reports/trial-balance', 'finance/reports/income-expenditure', 'finance/reports/balance-sheet',
            'finance/reports/cash-flow', 'finance/reports/budget', 'finance/reports/reconcile', 'finance/years', 'finance/journal', 'finance/journal/create'] as $page) {
            $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, $page))->assertOk();
        }
        $this->actingOnBranch($this->branchUser($branch, 'teacher'), $branch)->get($this->branchUrl($branch, 'finance/reports/trial-balance'))->assertForbidden();
    }

    public function test_budget_bank_reconciliation_and_year_closing(): void
    {
        $branch = $this->makeBranch();
        $accountant = $this->branchUser($branch, 'accountant');
        $ids = $this->books($branch, $accountant);
        $year = $this->inBranch($branch, fn () => FinancialYear::query()->firstOrFail());
        $this->assertSame('2025-26', $year->name);

        // Budget: only income / expense accounts; zero removes a line.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/reports/budget'), [
            'financial_year_id' => $year->id, 'amounts' => [$ids['utilities'] => 60000, $ids['stationery'] => 10000, $ids['cash'] => 999],
        ])->assertSessionHasNoErrors();
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/reports/budget'), [
            'financial_year_id' => $year->id, 'amounts' => [$ids['stationery'] => 0],
        ])->assertSessionHasNoErrors();
        $this->inBranch($branch, function () use ($ids, $year) {
            $this->assertSame([$ids['utilities']], Budget::query()->pluck('account_id')->map(fn ($id) => (int) $id)->all());
            $row = app(AccountingReportService::class)->budgetVsActual($year)->first(fn ($r) => $r['account']->id === $ids['utilities']);
            $this->assertEqualsWithDelta(60000, $row['budget'], 0.001);
        });

        // Bank reconciliation: tick the deposit that cleared.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'), [
            'type' => 'contra', 'date' => '2026-03-02', 'narration' => 'Deposit',
            'lines' => [['account_id' => $ids['bank'], 'debit' => 15000], ['account_id' => $ids['cash'], 'credit' => 15000]],
        ])->assertSessionHasNoErrors();
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'), [
            'type' => 'payment', 'date' => '2026-03-10', 'narration' => 'Cheque 1001 to supplier',
            'lines' => [['account_id' => $ids['utilities'], 'debit' => 4000], ['account_id' => $ids['bank'], 'credit' => 4000]],
        ])->assertSessionHasNoErrors();
        $deposit = $this->inBranch($branch, fn () => JournalLine::query()->where('account_id', $ids['bank'])->where('debit', '>', 0)->firstOrFail());
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, 'finance/reports/reconcile?account_id='.$ids['bank']))->assertOk()->assertSee('Cheque 1001');
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/reports/reconcile'), [
            'account_id' => $ids['bank'], 'as_of' => '2026-03-15', 'line_ids' => [$deposit->id], 'statement' => 15000,
        ])->assertSessionHasNoErrors();
        $this->inBranch($branch, function () use ($ids, $deposit) {
            $this->assertSame('2026-03-15', $deposit->fresh()->reconciled_on->toDateString());
            $this->assertSame(1, JournalLine::query()->where('account_id', $ids['bank'])->whereNull('reconciled_on')->count());
        });
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, 'finance/reports/reconcile?account_id='.$ids['bank']))->assertOk()->assertSee('Cheque 1001');

        // Closing the year blocks new entries dated in it; the years page still works; reopening allows them again.
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/years/{$year->id}/toggle"))->assertSessionHasNoErrors();
        $this->assertTrue($year->fresh()->is_closed);
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, 'finance/years'))->assertOk();
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, 'finance/reports/budget'))->assertOk();
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, 'finance/reports/income-expenditure'))->assertOk();
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, 'finance/journal'), [
            'type' => 'journal', 'date' => '2026-03-11', 'narration' => 'Late entry',
            'lines' => [['account_id' => $ids['utilities'], 'debit' => 100], ['account_id' => $ids['cash'], 'credit' => 100]],
        ])->assertSessionHasErrors('date');
        $this->actingOnBranch($accountant, $branch)->post($this->branchUrl($branch, "finance/years/{$year->id}/toggle"))->assertSessionHasNoErrors();
        $this->assertFalse($year->fresh()->is_closed);
    }

    public function test_chart_created_before_an_upgrade_gets_the_new_system_accounts(): void
    {
        $branch = $this->makeBranch();
        $this->inBranch($branch, function () {
            $chart = app(ChartOfAccounts::class);
            $chart->ensure();
            // Simulate a chart from Part 4-1: no salary-deductions account, and code 2400 already taken by the office.
            \App\Models\Finance\Account::query()->where('system_key', 'salary_deductions')->delete();
            $own = new \App\Models\Finance\Account(['code' => '2400', 'name' => 'Security deposits', 'type' => 'liability', 'is_group' => false, 'is_active' => true]);
            $own->save();
            foreach (range(1, 12) as $i) { // plenty of extra accounts (fee heads) — the old check stopped here
                \App\Models\Finance\Account::query()->create(['code' => (string) (4200 + $i), 'name' => "Fee head $i", 'type' => 'income', 'is_group' => false, 'is_active' => true]);
            }

            $account = $chart->get('salary_deductions');
            $this->assertSame('2401', $account->code);
            $this->assertSame('liability', $account->type);
            $this->assertSame('Security deposits', $own->fresh()->name);
        });
    }

    public function test_central_consolidated_report_adds_up_branches_of_the_institution_only(): void
    {
        $admin = User::factory()->role('institution-admin')->create();
        $north = $this->makeBranch($admin->institution, ['name' => 'North Campus']);
        $south = $this->makeBranch($admin->institution, ['name' => 'South Campus']);
        $foreign = $this->makeBranch(null, ['name' => 'Foreign Campus']);

        foreach ([[$north, 10000, 2000], [$south, 7000, 3000], [$foreign, 99999, 1]] as [$branch, $income, $expense]) {
            $this->inBranch($branch, function () use ($income, $expense) {
                $chart = app(ChartOfAccounts::class);
                $chart->ensure();
                $ledger = app(LedgerService::class);
                $ledger->post('receipt', '2026-02-01', 'Fees', [['account_id' => $chart->id('cash'), 'debit' => $income], ['account_id' => $chart->id('fee_income'), 'credit' => $income]]);
                $ledger->post('payment', '2026-02-02', 'Bills', [['account_id' => $chart->id('utilities'), 'debit' => $expense], ['account_id' => $chart->id('cash'), 'credit' => $expense]]);
            });
        }

        $this->actingCentral($admin)->get($this->centralUrl('super-admin/finance?from=2026-01-01&to=2026-03-15'))->assertOk()
            ->assertSee('North Campus')->assertSee('South Campus')->assertDontSee('Foreign Campus')
            ->assertSee('17,000.00')->assertSee('5,000.00')->assertSee('12,000.00');

        $accountant = $this->branchUser($north, 'accountant');
        $this->actingCentral($accountant)->get($this->centralUrl('super-admin/finance'))->assertRedirectContains('/login');
    }
}
