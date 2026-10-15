<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Branch;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeeStructure;
use App\Models\Finance\StudentFeeAdjustment;
use App\Models\Institution;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FeeInvoiceService;
use App\Services\Finance\FeePaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo fees for every demo branch (local/staging only): chart of accounts, fee heads, class fee structures,
 * two scholarships, invoices for the last three months of the current session and receipts for most students
 * (some partial, a few advance). Idempotent: a branch that already has fee heads is skipped.
 */
class DemoFinanceSeeder extends Seeder
{
    /** code => [name, name_bn, category] */
    protected array $heads = [
        'ADM' => ['Admission fee', 'ভর্তি ফি', 'admission'],
        'TUI' => ['Monthly tuition fee', 'মাসিক বেতন', 'tuition'],
        'SES' => ['Session fee', 'সেশন ফি', 'session'],
        'EXM' => ['Examination fee', 'পরীক্ষা ফি', 'exam'],
        'LAB' => ['Laboratory fee', 'ল্যাবরেটরি ফি', 'lab'],
        'LIB' => ['Library fee', 'লাইব্রেরি ফি', 'library'],
        'DEV' => ['Development fee', 'উন্নয়ন ফি', 'development'],
        'TRN' => ['Transport fee', 'পরিবহন ফি', 'transport'],
    ];

    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SSC')->first();
        if (! $institution) {
            $this->command?->warn('Demo institution not found — run DemoInstitutionSeeder first.');

            return;
        }

        foreach (Branch::query()->where('institution_id', $institution->id)->with('institution')->ordered()->get() as $branch) {
            tenant()->runForBranch($branch, function () use ($branch) {
                if (FeeHead::query()->exists()) {
                    return;
                }
                $session = AcademicSession::query()->current()->first();
                $admin = User::query()->where('email', "accounts.branch{$branch->sort_order}@demo.test")->first()
                    ?? User::query()->where('email', "admin.branch{$branch->sort_order}@demo.test")->first();
                if (! $session || ! $admin) {
                    return;
                }
                mt_srand($branch->id * 7727);

                $chart = app(ChartOfAccounts::class);
                $chart->ensure();
                $heads = [];
                $i = 0;
                foreach ($this->heads as $code => [$name, $nameBn, $category]) {
                    $heads[$code] = FeeHead::query()->create(['name' => $name, 'name_bn' => $nameBn, 'code' => $code, 'category' => $category,
                        'income_account_id' => $chart->incomeAccountForHead($name, $nameBn)->id, 'is_active' => true, 'sort_order' => ++$i]);
                }

                $startMonth = (int) CarbonImmutable::parse($session->starts_on)->month;
                foreach (SchoolClass::query()->active()->get() as $class) {
                    $level = max(1, (int) $class->numeric_order);
                    $rows = [
                        'TUI' => [500 + $level * 100, range(1, 12)],
                        'SES' => [1000 + $level * 100, [$startMonth]],
                        'DEV' => [800, [$startMonth]],
                        'EXM' => [300 + $level * 20, [6, 11]],
                        'LIB' => [200, [$startMonth]],
                    ];
                    if ($level >= 9) {
                        $rows['LAB'] = [400, [$startMonth, 7]];
                    }
                    foreach ($rows as $code => [$amount, $months]) {
                        FeeStructure::query()->create(['academic_session_id' => $session->id, 'school_class_id' => $class->id, 'fee_head_id' => $heads[$code]->id,
                            'amount' => $amount, 'months' => $months]);
                    }
                }

                // Two scholarships and one transport user.
                $students = Student::query()->where('status', 'active')->orderBy('id')->limit(40)->get();
                foreach ($students->take(2) as $n => $s) {
                    $adj = new StudentFeeAdjustment(['student_id' => $s->id, 'academic_session_id' => $session->id, 'fee_head_id' => $heads['TUI']->id,
                        'type' => 'scholarship', 'mode' => 'percent', 'value' => $n === 0 ? 100 : 50, 'reason' => 'Merit scholarship', 'is_active' => true]);
                    $adj->forceFill(['approved_by' => $admin->id])->save();
                }
                if ($s = $students->get(2)) {
                    $adj = new StudentFeeAdjustment(['student_id' => $s->id, 'academic_session_id' => $session->id, 'fee_head_id' => $heads['TRN']->id,
                        'type' => 'extra_fee', 'mode' => 'fixed', 'value' => 600, 'months' => range(1, 12), 'reason' => 'School bus — route 1', 'is_active' => true]);
                    $adj->forceFill(['approved_by' => $admin->id])->save();
                }

                $invoices = app(FeeInvoiceService::class);
                $first = CarbonImmutable::parse($session->starts_on)->startOfMonth();
                $month = CarbonImmutable::today()->startOfMonth()->subMonths(2);
                if ($month->lt($first)) {
                    $month = $first;
                }
                while ($month->lte(CarbonImmutable::today())) {
                    $invoices->generateForPeriod($month->format('Y-m'), null, $admin);
                    $month = $month->addMonth();
                }

                // Receipts: most students pay in full, some part, a few in advance, the rest owe.
                $payments = app(FeePaymentService::class);
                $methods = ['cash', 'cash', 'cash', 'bkash', 'nagad', 'bank'];
                foreach (FeeInvoice::query()->open()->get()->groupBy('student_id') as $studentId => $open) {
                    $roll = mt_rand(1, 100);
                    if ($roll > 80) {
                        continue;
                    }
                    $due = $open->sum('balance');
                    $amount = $roll <= 55 ? $due : ($roll <= 70 ? round($due * 0.5, 0) : $due + 1000);
                    $method = $methods[array_rand($methods)];
                    $payments->collect(Student::query()->find($studentId), (float) $amount, $method, null,
                        CarbonImmutable::today()->subDays(mt_rand(0, 20))->max($open->min('issue_date')),
                        $method === 'cash' ? null : strtoupper(substr($method, 0, 2)).mt_rand(10000000, 99999999), null, [], $admin);
                }
            });
        }

        $this->command?->info('Demo fee heads, structures, invoices and receipts seeded.');
    }
}
