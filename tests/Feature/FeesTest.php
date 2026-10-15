<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Finance\Account;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Finance\FeeStructure;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use App\Models\Finance\StudentFeeAdjustment;
use App\Models\Institution;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FeeInvoiceService;
use App\Services\Finance\FeePaymentService;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
    }

    /** Tuition 1000/month, session fee 2000 in March, exam fee 500 in June for class Six; students A, B (section A). */
    protected function fixture(Branch $branch): array
    {
        $setup = $this->academicSetup($branch);
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $b = $this->enrolStudent($branch, $setup, 'Babul');

        $heads = $this->inBranch($branch, function () use ($setup) {
            app(ChartOfAccounts::class)->ensure();
            $heads = [
                'TUI' => FeeHead::query()->create(['name' => 'Tuition', 'code' => 'TUI', 'category' => 'tuition', 'is_active' => true]),
                'SES' => FeeHead::query()->create(['name' => 'Session fee', 'code' => 'SES', 'category' => 'session', 'is_active' => true]),
                'EXM' => FeeHead::query()->create(['name' => 'Exam fee', 'code' => 'EXM', 'category' => 'exam', 'is_active' => true]),
            ];
            foreach ([['TUI', 1000, range(1, 12)], ['SES', 2000, [3]], ['EXM', 500, [6]]] as [$code, $amount, $months]) {
                FeeStructure::query()->create(['academic_session_id' => $setup['session']->id, 'school_class_id' => $setup['class']->id,
                    'fee_head_id' => $heads[$code]->id, 'amount' => $amount, 'months' => $months]);
            }

            return $heads;
        });

        return compact('setup', 'a', 'b', 'heads');
    }

    protected function assertBooksBalance(Branch $branch): void
    {
        $this->inBranch($branch, function () {
            $this->assertEqualsWithDelta((float) JournalLine::query()->sum('debit'), (float) JournalLine::query()->sum('credit'), 0.001);
            $ledger = app(LedgerService::class);
            $receivable = $ledger->balance(app(ChartOfAccounts::class)->get('fees_receivable'));
            $this->assertEqualsWithDelta((float) FeeInvoice::query()->open()->sum('balance'), $receivable, 0.001, 'Receivable account = open invoice balances');
            $advance = $ledger->balance(app(ChartOfAccounts::class)->get('student_advance'));
            $this->assertEqualsWithDelta((float) FeePayment::query()->where('status', 'completed')->sum('unallocated'), $advance, 0.001, 'Advance account = unused credit');
        });
    }

    public function test_monthly_generation_applies_structures_and_discounts_and_is_idempotent(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->inBranch($branch, function () use ($f, $admin) {
            $adj = new StudentFeeAdjustment(['student_id' => $f['b']->id, 'academic_session_id' => $f['setup']['session']->id, 'fee_head_id' => $f['heads']['TUI']->id,
                'type' => 'scholarship', 'mode' => 'percent', 'value' => 50, 'reason' => 'Merit', 'is_active' => true]);
            $adj->forceFill(['approved_by' => $admin->id])->save();
        });

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'finance/invoices/generate'), ['period' => '2026-03'])->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'finance/invoices/generate'), ['period' => '2026-03'])->assertSessionHasNoErrors();

        $invoices = $this->inBranch($branch, fn () => FeeInvoice::query()->with('items')->get()->keyBy('student_id'));
        $this->assertCount(2, $invoices);
        $this->assertSame(3000.0, $invoices[$f['a']->id]->total);                   // tuition 1000 + session 2000
        $this->assertSame(500.0, $invoices[$f['b']->id]->discount);                 // 50 % of tuition
        $this->assertSame(2500.0, $invoices[$f['b']->id]->total);
        $this->assertSame('2026-03-10', $invoices[$f['a']->id]->due_date->toDateString());

        // April bills tuition only.
        $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-04'));
        $this->assertSame(1000.0, $this->inBranch($branch, fn () => FeeInvoice::query()->where('period', '2026-04')->where('student_id', $f['a']->id)->sole()->total));

        $this->assertBooksBalance($branch);
        $this->inBranch($branch, function () {
            $discount = app(LedgerService::class)->balance(app(ChartOfAccounts::class)->get('fee_discount'));
            $this->assertSame(1000.0, $discount); // March + April scholarship
        });
    }

    public function test_sibling_discount_goes_to_the_younger_child_sharing_a_guardian(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $this->inBranch($branch, function () use ($f) {
            app(FinanceSettings::class)->save(['sibling_discount_percent' => 10]);
            $guardian = $f['a']->guardians()->first();
            $f['b']->guardians()->attach($guardian->id, ['relation' => 'father', 'is_primary' => false]);
            app(FeeInvoiceService::class)->generateForPeriod('2026-04');
        });

        $invoices = $this->inBranch($branch, fn () => FeeInvoice::query()->get()->keyBy('student_id'));
        $this->assertSame(0.0, $invoices[$f['a']->id]->discount);
        $this->assertSame(100.0, $invoices[$f['b']->id]->discount);
    }

    public function test_partial_payment_over_payment_advance_and_automatic_credit(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'accountant');
        $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-03'));
        $url = $this->branchUrl($branch, "finance/students/{$f['a']->id}/payments");

        $this->actingOnBranch($admin, $branch)->post($url, ['amount' => 1200, 'method' => 'cash', 'date' => '2026-03-15'])->assertSessionHasNoErrors();
        $inv = $this->inBranch($branch, fn () => FeeInvoice::query()->where('student_id', $f['a']->id)->sole());
        $this->assertSame(['partial', 1200.0, 1800.0], [$inv->status, $inv->paid, $inv->balance]);

        // bKash needs a transaction id; future dates are refused.
        $this->actingOnBranch($admin, $branch)->post($url, ['amount' => 100, 'method' => 'bkash', 'date' => '2026-03-15'])->assertSessionHasErrors('reference');
        $this->actingOnBranch($admin, $branch)->post($url, ['amount' => 100, 'method' => 'cash', 'date' => '2026-03-16'])->assertSessionHasErrors('date');

        $this->actingOnBranch($admin, $branch)->post($url, ['amount' => 2800, 'method' => 'bkash', 'reference' => 'BK12345678', 'date' => '2026-03-15'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $this->inBranch($branch, fn () => $inv->fresh()->status));
        $this->assertSame(1000.0, $this->inBranch($branch, fn () => app(FeePaymentService::class)->credit($f['a'])));

        // April's invoice is settled from the advance automatically.
        $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-04'));
        $april = $this->inBranch($branch, fn () => FeeInvoice::query()->where('period', '2026-04')->where('student_id', $f['a']->id)->sole());
        $this->assertSame(['paid', 0.0], [$april->status, $april->balance]);
        $this->assertSame(0.0, $this->inBranch($branch, fn () => app(FeePaymentService::class)->credit($f['a'])));

        // Receipt money went to the mobile-banking account.
        $this->inBranch($branch, fn () => $this->assertSame(2800.0, app(LedgerService::class)->balance(app(ChartOfAccounts::class)->get('mobile'))));
        $this->assertBooksBalance($branch);

        // Ledger shows charges and payments with the running balance ending at zero.
        $ledger = $this->inBranch($branch, fn () => app(FeePaymentService::class)->ledger($f['a']));
        $this->assertSame(0.0, $ledger->last()['balance']);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "finance/students/{$f['a']->id}?tab=ledger"))->assertOk()->assertSee('Anika');
    }

    public function test_reversing_a_receipt_reopens_invoices_including_advance_applications(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'accountant');
        $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-03'));
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/students/{$f['a']->id}/payments"), ['amount' => 4000, 'method' => 'cash', 'date' => '2026-03-15']);
        $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-04')); // uses 1000 advance
        $payment = $this->inBranch($branch, fn () => FeePayment::query()->sole());

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/payments/{$payment->id}/reverse"), ['reason' => 'Entered for the wrong student'])->assertSessionHasNoErrors();

        $this->assertSame('reversed', $this->inBranch($branch, fn () => $payment->fresh()->status));
        $this->assertSame(['unpaid', 'unpaid'], $this->inBranch($branch, fn () => FeeInvoice::query()->where('student_id', $f['a']->id)->orderBy('id')->pluck('status')->all()));
        $this->assertSame(4000.0, $this->inBranch($branch, fn () => app(FeePaymentService::class)->outstanding($f['a'])));
        $this->assertBooksBalance($branch);

        // Posted entries are never edited: the originals are marked reversed and mirrored.
        $this->assertGreaterThanOrEqual(2, $this->inBranch($branch, fn () => JournalEntry::query()->where('status', 'reversed')->count()));
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/payments/{$payment->id}/reverse"), ['reason' => 'again please'])->assertSessionHasErrors('reason');
    }

    public function test_cancelling_a_paid_invoice_keeps_the_money_as_advance_which_can_be_refunded(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'accountant');
        $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-03'));
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/students/{$f['a']->id}/payments"), ['amount' => 3000, 'method' => 'cash', 'date' => '2026-03-15']);
        $inv = $this->inBranch($branch, fn () => FeeInvoice::query()->where('student_id', $f['a']->id)->sole());

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/invoices/{$inv->id}/cancel"), ['reason' => 'Student left before March'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->inBranch($branch, fn () => $inv->fresh()->status));
        $this->assertSame(3000.0, $this->inBranch($branch, fn () => app(FeePaymentService::class)->credit($f['a'])));
        $this->assertBooksBalance($branch);

        $refundUrl = $this->branchUrl($branch, "finance/students/{$f['a']->id}/refunds");
        $this->actingOnBranch($admin, $branch)->post($refundUrl, ['amount' => 5000, 'method' => 'cash', 'date' => '2026-03-15', 'reason' => 'Refund to father'])->assertSessionHasErrors('amount');
        $this->actingOnBranch($admin, $branch)->post($refundUrl, ['amount' => 3000, 'method' => 'cash', 'date' => '2026-03-15', 'reason' => 'Refund to father'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->inBranch($branch, fn () => app(FeePaymentService::class)->credit($f['a'])));
        $this->inBranch($branch, fn () => $this->assertSame(0.0, app(LedgerService::class)->balance(app(ChartOfAccounts::class)->get('cash'))));
        $this->assertBooksBalance($branch);

        // A refunded receipt can no longer be reversed.
        $payment = $this->inBranch($branch, fn () => FeePayment::query()->sole());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/payments/{$payment->id}/reverse"), ['reason' => 'Try to reverse'])->assertSessionHasErrors('reason');
    }

    public function test_late_fee_is_charged_once_and_waiver_writes_off_the_balance(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'accountant');
        $this->inBranch($branch, function () {
            app(FinanceSettings::class)->save(['late_fee_mode' => 'fixed', 'late_fee_amount' => 50, 'late_fee_grace_days' => 2]);
            app(FeeInvoiceService::class)->generateForPeriod('2026-03'); // due 10 March
        });

        $this->assertSame(2, $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->applyLateFees()));
        $this->assertSame(0, $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->applyLateFees()));
        $inv = $this->inBranch($branch, fn () => FeeInvoice::query()->where('student_id', $f['a']->id)->sole());
        $this->assertSame([50.0, 3050.0], [$inv->late_fee, $inv->total]);

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/invoices/{$inv->id}/waive"), ['amount' => 5000, 'reason' => 'Hardship case'])->assertSessionHasErrors('amount');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "finance/invoices/{$inv->id}/waive"), ['amount' => 50, 'reason' => 'Late fee waived by principal'])->assertSessionHasNoErrors();
        $this->assertSame([50.0, 3000.0, 'partial'], $this->inBranch($branch, fn () => [$inv->fresh()->waived, $inv->fresh()->balance, $inv->fresh()->status]));
        $this->assertBooksBalance($branch);
    }

    public function test_ledger_refuses_unbalanced_entries_and_closed_years(): void
    {
        $branch = $this->makeBranch();
        $this->inBranch($branch, function () {
            $chart = app(ChartOfAccounts::class);
            $ledger = app(LedgerService::class);
            try {
                $ledger->post('journal', '2026-03-01', 'Bad', [['account_id' => $chart->id('cash'), 'debit' => 100], ['account_id' => $chart->id('fund'), 'credit' => 90]]);
                $this->fail('Unbalanced entry accepted');
            } catch (\Illuminate\Validation\ValidationException) {
            }
            try {
                $ledger->post('journal', '2026-03-01', 'Group', [['account_id' => $chart->id('assets'), 'debit' => 100], ['account_id' => $chart->id('fund'), 'credit' => 100]]);
                $this->fail('Posting to a group account accepted');
            } catch (\Illuminate\Validation\ValidationException) {
            }
            $entry = $ledger->post('journal', '2026-03-01', 'Opening cash', [['account_id' => $chart->id('cash'), 'debit' => 100], ['account_id' => $chart->id('opening_balance'), 'credit' => 100]]);
            $this->assertStringContainsString('-JV', $entry->entry_no);
            $this->assertSame('2025-26', $entry->fresh()->financial_year_id ? \App\Models\Finance\FinancialYear::query()->find($entry->financial_year_id)->name : null);

            \App\Models\Finance\FinancialYear::query()->where('id', $entry->financial_year_id)->update(['is_closed' => true]);
            try {
                $ledger->post('journal', '2026-03-02', 'Late', [['account_id' => $chart->id('cash'), 'debit' => 1], ['account_id' => $chart->id('opening_balance'), 'credit' => 1]]);
                $this->fail('Posting into a closed year accepted');
            } catch (\Illuminate\Validation\ValidationException) {
            }
        });
    }

    public function test_access_is_limited_by_permission_branch_and_portal(): void
    {
        $institution = Institution::factory()->create();
        $branch = $this->makeBranch($institution);
        $other = $this->makeBranch($institution);
        $f = $this->fixture($branch);
        $this->inBranch($branch, fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-03'));
        $inv = $this->inBranch($branch, fn () => FeeInvoice::query()->where('student_id', $f['a']->id)->sole());
        $invB = $this->inBranch($branch, fn () => FeeInvoice::query()->where('student_id', $f['b']->id)->sole());

        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'finance/invoices'))->assertForbidden();
        $otherAdmin = $this->branchUser($other, 'branch-admin');
        $this->actingOnBranch($otherAdmin, $other)->get($this->branchUrl($other, "finance/invoices/{$inv->id}"))->assertNotFound();

        $admin = $this->branchUser($branch, 'branch-admin');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'finance/invoices?status=unpaid'))->assertOk()->assertSee($inv->invoice_no);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "finance/invoices/{$inv->id}?print=1"))->assertOk()->assertSee('Anika');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'finance/reports/dues'))->assertOk()->assertSee('Babul');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'finance/reports/dues?export=csv'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'finance/reports/collection'))->assertOk();
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'finance/accounts'))->assertOk()->assertSee('Student fees receivable');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'finance/structures'))->assertOk()->assertSee('Tuition');

        // Student portal: own invoices only.
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$f['a']->id}/portal/student"), ['password' => 'secret123']);
        $studentUser = User::query()->where('username', strtolower($f['a']->student_code))->sole();
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, 'student/fees'))->assertOk()->assertSee('3,000.00');
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, "student/fees/invoices/{$inv->id}"))->assertOk();
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, "student/fees/invoices/{$invB->id}"))->assertNotFound();
    }

    public function test_fee_structure_screen_saves_amounts_and_months(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'accountant');
        $payload = ['academic_session_id' => $f['setup']['session']->id, 'school_class_id' => $f['setup']['class']->id, 'heads' => [
            $f['heads']['TUI']->id => ['amount' => 1200, 'months' => range(1, 12)],
            $f['heads']['EXM']->id => ['amount' => 600, 'months' => []],
        ]];

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'finance/structures'), $payload)->assertSessionHasErrors("heads.{$f['heads']['EXM']->id}.months");
        $payload['heads'][$f['heads']['EXM']->id]['months'] = [6, 11];
        $payload['heads'][$f['heads']['SES']->id] = ['amount' => '', 'months' => [3]];
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'finance/structures'), $payload)->assertSessionHasNoErrors();

        $rows = $this->inBranch($branch, fn () => FeeStructure::query()->get()->keyBy('fee_head_id'));
        $this->assertSame(1200.0, $rows[$f['heads']['TUI']->id]->amount);
        $this->assertSame([6, 11], $rows[$f['heads']['EXM']->id]->months);
        $this->assertFalse($rows->has($f['heads']['SES']->id)); // cleared
        $this->assertTrue($this->inBranch($branch, fn () => Account::query()->where('system_key', 'fees_receivable')->exists()));
    }
}
