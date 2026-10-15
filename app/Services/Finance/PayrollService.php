<?php

namespace App\Services\Finance;

use App\Models\Attendance\StaffAttendance;
use App\Models\Finance\Account;
use App\Models\Finance\EmployeeSalary;
use App\Models\Finance\PayrollItem;
use App\Models\Finance\PayrollRun;
use App\Models\HR\Employee;
use App\Models\HR\LeaveApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly payroll.
 *   prepare   salary sheet from each active employee's salary structure; unpaid days (absent without leave +
 *             leave without pay, from staff attendance) are deducted at basic ÷ days-basis per day
 *   approve   Dr Salaries & allowances (earned) / Cr Salary deductions payable (PF, tax …) + Cr Salaries payable (net)
 *   pay       Dr Salaries payable / Cr cash-bank
 *   cancel    draft: discarded; approved or paid: every posted entry reversed
 * One live (non-cancelled) run per month. The preparer does not approve their own sheet.
 */
class PayrollService
{
    public function __construct(protected LedgerService $ledger, protected ChartOfAccounts $chart, protected FinanceSettings $settings) {}

    public function prepare(string $period, User $user): PayrollRun
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => __('The month must look like 2026-07.')]);
        }
        if (PayrollRun::query()->where('period', $period)->where('status', '!=', 'cancelled')->exists()) {
            throw ValidationException::withMessages(['period' => __('A salary sheet for :month already exists. Cancel it first to prepare it again.', ['month' => $period])]);
        }
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $end = $start->endOfMonth();

        $salaries = EmployeeSalary::query()->with('employee')->whereDate('effective_from', '<=', $end->toDateString())->get()
            ->filter(fn ($s) => $s->employee && $s->employee->status === 'active' && (! $s->employee->joining_date || $s->employee->joining_date->lte($end)));
        if ($salaries->isEmpty()) {
            throw ValidationException::withMessages(['period' => __('No active employee has a salary structure yet.')]);
        }

        $unpaid = $this->settings->get('payroll_deduct_unpaid') ? $this->unpaidDays($salaries->pluck('employee_id')->all(), $start, $end) : [];
        $basis = max(1, (int) $this->settings->get('payroll_days_basis'));

        return DB::transaction(function () use ($period, $user, $salaries, $unpaid, $basis) {
            $run = new PayrollRun(['period' => $period]);
            $run->forceFill(['status' => 'draft', 'prepared_by' => $user->id])->save();

            foreach ($salaries as $salary) {
                $days = (int) ($unpaid[$salary->employee_id] ?? 0);
                $this->writeItem($run, $salary->employee_id, $salary->basic, $salary->allowances ?? [], $salary->deductions ?? [], $days,
                    min($salary->basic, round($salary->basic / $basis * $days, 2)));
            }

            return $this->totals($run);
        });
    }

    /** Draft only: add a bonus / arrear or an extra deduction to one employee's line. */
    public function adjust(PayrollItem $item, float $bonus, float $deduction, ?string $remarks): PayrollItem
    {
        $run = $item->run;
        if ($run->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Only a draft salary sheet can be changed.')]);
        }
        $allowances = array_values(array_filter($item->allowances ?? [], fn ($a) => ($a['name'] ?? '') !== __('Bonus / arrear')));
        $deductions = array_values(array_filter($item->deductions ?? [], fn ($d) => ($d['name'] ?? '') !== __('Other deduction')));
        if ($bonus > 0) {
            $allowances[] = ['name' => __('Bonus / arrear'), 'amount' => round($bonus, 2)];
        }
        if ($deduction > 0) {
            $deductions[] = ['name' => __('Other deduction'), 'amount' => round($deduction, 2)];
        }

        DB::transaction(function () use ($item, $run, $allowances, $deductions, $remarks) {
            $this->writeItem($run, $item->employee_id, $item->basic, $allowances, $deductions, $item->unpaid_days, $item->unpaid_deduction, $remarks, $item);
            $this->totals($run);
        });

        return $item->fresh();
    }

    public function approve(PayrollRun $run, User $user): PayrollRun
    {
        if ($run->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Only a draft salary sheet can be approved.')]);
        }
        if ((int) $run->prepared_by === (int) $user->id) {
            throw ValidationException::withMessages(['status' => __('The salary sheet must be approved by someone other than the person who prepared it.')]);
        }
        $items = $run->items()->get();
        $earned = round($items->sum(fn ($i) => $i->gross - $i->unpaid_deduction), 2);
        $withheld = round($items->sum(fn ($i) => $i->total_deductions - $i->unpaid_deduction), 2);
        $net = round($items->sum('net'), 2);
        if ($net < 0) {
            throw ValidationException::withMessages(['status' => __('Deductions exceed the salary of at least one employee.')]);
        }

        return DB::transaction(function () use ($run, $user, $earned, $withheld, $net) {
            $lines = [
                ['account_id' => $this->chart->id('salaries'), 'debit' => $earned, 'description' => __('Salary sheet :month', ['month' => $run->period])],
                ['account_id' => $this->chart->id('salary_payable'), 'credit' => $net],
            ];
            if ($withheld > 0) {
                $lines[] = ['account_id' => $this->chart->id('salary_deductions'), 'credit' => $withheld, 'description' => __('PF, tax and other deductions')];
            }
            $this->ledger->post('payroll', CarbonImmutable::createFromFormat('Y-m-d', $run->period.'-01')->endOfMonth(),
                __('Salaries for :month', ['month' => $run->monthLabel()]), $lines, $run, $user);
            $run->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();

            return $run;
        });
    }

    public function pay(PayrollRun $run, Account $from, \DateTimeInterface|string $date, User $user): PayrollRun
    {
        if ($run->status !== 'approved') {
            throw ValidationException::withMessages(['status' => __('Only an approved salary sheet can be paid.')]);
        }
        if (! Account::query()->money()->whereKey($from->id)->exists()) {
            throw ValidationException::withMessages(['account_id' => __('Choose the cash, bank or mobile-banking account salaries are paid from.')]);
        }

        return DB::transaction(function () use ($run, $from, $date, $user) {
            $this->ledger->post('payment', $date, __('Salary payment for :month', ['month' => $run->monthLabel()]), [
                ['account_id' => $this->chart->id('salary_payable'), 'debit' => $run->net],
                ['account_id' => $from->id, 'credit' => $run->net],
            ], $run, $user);
            $run->forceFill(['status' => 'paid', 'paid_from_account_id' => $from->id, 'paid_on' => CarbonImmutable::parse($date)->toDateString()])->save();

            return $run;
        });
    }

    public function cancel(PayrollRun $run, string $reason, User $user): PayrollRun
    {
        if ($run->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => __('This salary sheet is already cancelled.')]);
        }

        return DB::transaction(function () use ($run, $reason, $user) {
            foreach ($this->ledger->entriesFor($run)->filter(fn ($e) => $e->status === 'posted' && ! $e->reversal_of_id) as $entry) {
                $this->ledger->reverse($entry, $reason, $user);
            }
            $run->forceFill(['status' => 'cancelled', 'notes' => mb_substr($reason, 0, 255)])->save();

            return $run;
        });
    }

    /** Absent days without leave + days on leave without pay, per employee. @return array<int,int> */
    public function unpaidDays(array $employeeIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = StaffAttendance::query()->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())
            ->whereIn('status', ['absent', 'leave'])->get(['employee_id', 'status', 'leave_application_id']);

        $unpaidLeaves = LeaveApplication::query()->whereIn('id', $rows->pluck('leave_application_id')->filter()->unique())
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))->pluck('id')->flip();

        $out = [];
        foreach ($rows as $r) {
            $status = $r->status instanceof \BackedEnum ? $r->status->value : (string) $r->status;
            if ($status === 'absent' || ($status === 'leave' && $r->leave_application_id && $unpaidLeaves->has($r->leave_application_id))) {
                $out[$r->employee_id] = ($out[$r->employee_id] ?? 0) + 1;
            }
        }

        return $out;
    }

    protected function writeItem(PayrollRun $run, int $employeeId, float $basic, array $allowances, array $deductions, int $unpaidDays, float $unpaidDeduction,
        ?string $remarks = null, ?PayrollItem $item = null): PayrollItem
    {
        $clean = fn (array $rows) => array_values(array_filter(array_map(fn ($r) => ['name' => mb_substr((string) ($r['name'] ?? ''), 0, 60), 'amount' => round((float) ($r['amount'] ?? 0), 2)], $rows),
            fn ($r) => $r['name'] !== '' && $r['amount'] > 0));
        $allowances = $clean($allowances);
        $deductions = $clean($deductions);
        $gross = round($basic + array_sum(array_column($allowances, 'amount')), 2);
        $total = round(array_sum(array_column($deductions, 'amount')) + $unpaidDeduction, 2);

        $attributes = ['payroll_run_id' => $run->id, 'employee_id' => $employeeId, 'basic' => $basic, 'allowances' => $allowances, 'gross' => $gross,
            'deductions' => $deductions, 'unpaid_days' => $unpaidDays, 'unpaid_deduction' => $unpaidDeduction, 'total_deductions' => $total,
            'net' => round($gross - $total, 2), 'remarks' => $remarks !== null ? mb_substr($remarks, 0, 255) : $item?->remarks];

        if ($item) {
            $item->update($attributes);

            return $item;
        }

        return PayrollItem::query()->create($attributes);
    }

    protected function totals(PayrollRun $run): PayrollRun
    {
        $items = $run->items()->get();
        $run->forceFill(['employees' => $items->count(), 'gross' => round($items->sum('gross'), 2), 'deductions' => round($items->sum('total_deductions'), 2),
            'net' => round($items->sum('net'), 2)])->save();

        return $run;
    }
}
