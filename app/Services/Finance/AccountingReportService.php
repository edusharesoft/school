<?php

namespace App\Services\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\Budget;
use App\Models\Finance\FinancialYear;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Financial statements of the branch in context, computed only from posted journal lines
 * (reversed entries and their reversals both stay in the books and cancel each other out).
 */
class AccountingReportService
{
    public function __construct(protected ChartOfAccounts $chart) {}

    /** @return array{account:Account, opening:float, rows:Collection, closing:float, debit:float, credit:float} */
    public function generalLedger(Account $account, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $opening = $this->netBefore([$account->id], $from)[$account->id] ?? 0.0;
        $sign = $account->isDebitNormal() ? 1 : -1;
        $balance = round($sign * $opening, 2);

        $lines = JournalLine::query()->with('entry:id,entry_no,narration,type,status')->where('account_id', $account->id)
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')->orderBy('journal_entry_id')->orderBy('id')->get();

        $rows = $lines->map(function (JournalLine $l) use (&$balance, $sign) {
            $balance = round($balance + $sign * ($l->debit - $l->credit), 2);

            return ['line' => $l, 'balance' => $balance];
        });

        return ['account' => $account, 'opening' => round($sign * $opening, 2), 'rows' => $rows, 'closing' => $balance,
            'debit' => round($lines->sum('debit'), 2), 'credit' => round($lines->sum('credit'), 2)];
    }

    /** Balances of every posting account up to a date, on the debit or credit side. */
    public function trialBalance(CarbonImmutable $asOf): array
    {
        $accounts = Account::query()->where('is_group', false)->orderBy('code')->get();
        $net = $this->netBefore($accounts->pluck('id')->all(), $asOf->addDay());

        $rows = $accounts->map(fn (Account $a) => ['account' => $a, 'debit' => max(0, round($net[$a->id] ?? 0, 2)), 'credit' => max(0, round(-($net[$a->id] ?? 0), 2))])
            ->filter(fn ($r) => $r['debit'] != 0 || $r['credit'] != 0)->values();

        return ['rows' => $rows, 'debit' => round($rows->sum('debit'), 2), 'credit' => round($rows->sum('credit'), 2)];
    }

    /** Income and expenditure (receipts & payments by nature) for a period; surplus = income − expenditure. */
    public function incomeExpenditure(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $movements = $this->movements($from, $to);
        $accounts = Account::query()->whereIn('type', ['income', 'expense'])->where('is_group', false)->orderBy('code')->get();

        $income = $accounts->where('type', 'income')->map(fn ($a) => ['account' => $a, 'amount' => round(-($movements[$a->id] ?? 0), 2)])->filter(fn ($r) => $r['amount'] != 0)->values();
        $expense = $accounts->where('type', 'expense')->map(fn ($a) => ['account' => $a, 'amount' => round($movements[$a->id] ?? 0, 2)])->filter(fn ($r) => $r['amount'] != 0)->values();
        $totalIncome = round($income->sum('amount'), 2);
        $totalExpense = round($expense->sum('amount'), 2);

        return ['income' => $income, 'expense' => $expense, 'totalIncome' => $totalIncome, 'totalExpense' => $totalExpense, 'surplus' => round($totalIncome - $totalExpense, 2)];
    }

    /** Balance sheet at a date; accumulated surplus = all income − all expenses up to the date. */
    public function balanceSheet(CarbonImmutable $asOf): array
    {
        $accounts = Account::query()->whereIn('type', ['asset', 'liability', 'equity', 'income', 'expense'])->where('is_group', false)->orderBy('code')->get();
        $net = $this->netBefore($accounts->pluck('id')->all(), $asOf->addDay());
        $side = fn (string $type, int $sign) => $accounts->where('type', $type)->map(fn ($a) => ['account' => $a, 'amount' => round($sign * ($net[$a->id] ?? 0), 2)])
            ->filter(fn ($r) => $r['amount'] != 0)->values();

        $assets = $side('asset', 1);
        $liabilities = $side('liability', -1);
        $equity = $side('equity', -1);
        $surplus = round(-$accounts->whereIn('type', ['income', 'expense'])->sum(fn ($a) => $net[$a->id] ?? 0), 2);

        $totalAssets = round($assets->sum('amount'), 2);
        $totalLiabilities = round($liabilities->sum('amount'), 2);
        $totalEquity = round($equity->sum('amount') + $surplus, 2);

        return compact('assets', 'liabilities', 'equity', 'surplus', 'totalAssets', 'totalLiabilities', 'totalEquity')
            + ['balanced' => abs($totalAssets - $totalLiabilities - $totalEquity) < 0.01];
    }

    /**
     * Cash flow (direct method): money accounts' receipts and payments grouped by the other side of each voucher.
     *
     * @return array{opening:float, closing:float, inflows:Collection, outflows:Collection, totalIn:float, totalOut:float, accounts:Collection}
     */
    public function cashFlow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $money = Account::query()->whereNotNull('kind')->where('is_group', false)->get();
        $ids = $money->pluck('id')->all();
        $openingNet = $this->netBefore($ids, $from);
        $opening = round(array_sum($openingNet), 2);

        $entries = JournalEntry::query()->with('lines.account:id,code,name,type,kind')->whereHas('lines', fn ($q) => $q->whereIn('account_id', $ids))
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->get();

        $in = [];
        $out = [];
        foreach ($entries as $entry) {
            $cash = round($entry->lines->whereIn('account_id', $ids)->sum(fn ($l) => $l->debit - $l->credit), 2);
            if ($cash == 0.0) {
                continue; // contra between cash and bank
            }
            $others = $entry->lines->whereNotIn('account_id', $ids);
            $weight = max(0.01, $others->sum(fn ($l) => abs($l->debit - $l->credit)));
            foreach ($others as $l) {
                $label = $this->flowLabel($l->account);
                $share = round(abs($cash) * abs($l->debit - $l->credit) / $weight, 2);
                if ($cash > 0) {
                    $in[$label] = round(($in[$label] ?? 0) + $share, 2);
                } else {
                    $out[$label] = round(($out[$label] ?? 0) + $share, 2);
                }
            }
        }
        arsort($in);
        arsort($out);
        $totalIn = round(array_sum($in), 2);
        $totalOut = round(array_sum($out), 2);

        $closingNet = $this->netBefore($ids, $to->addDay());

        return [
            'opening' => $opening, 'closing' => round(array_sum($closingNet), 2),
            'inflows' => collect($in), 'outflows' => collect($out), 'totalIn' => $totalIn, 'totalOut' => $totalOut,
            'accounts' => $money->map(fn ($a) => ['account' => $a, 'opening' => round($openingNet[$a->id] ?? 0, 2), 'closing' => round($closingNet[$a->id] ?? 0, 2)]),
        ];
    }

    /** Budget vs actual for income & expense accounts of a financial year. */
    public function budgetVsActual(FinancialYear $year): Collection
    {
        $budgets = Budget::query()->where('financial_year_id', $year->id)->pluck('amount', 'account_id');
        $movements = $this->movements(CarbonImmutable::parse($year->starts_on), CarbonImmutable::parse($year->ends_on));

        return Account::query()->whereIn('type', ['income', 'expense'])->where('is_group', false)->where('is_active', true)->orderBy('code')->get()
            ->map(function (Account $a) use ($budgets, $movements) {
                $actual = round(($a->type === 'income' ? -1 : 1) * ($movements[$a->id] ?? 0), 2);
                $budget = (float) ($budgets[$a->id] ?? 0);

                return ['account' => $a, 'budget' => $budget, 'actual' => $actual, 'variance' => round($budget - $actual, 2),
                    'used' => $budget > 0 ? round($actual / $budget * 100, 1) : null];
            });
    }

    /** Σ(debit − credit) per account strictly before a date. @return array<int,float> */
    protected function netBefore(array $accountIds, CarbonImmutable $before): array
    {
        if ($accountIds === []) {
            return [];
        }

        return JournalLine::query()->whereIn('account_id', $accountIds)->whereDate('date', '<', $before->toDateString())
            ->selectRaw('account_id, SUM(debit) - SUM(credit) as net')->groupBy('account_id')->toBase()->pluck('net', 'account_id')
            ->map(fn ($v) => (float) $v)->all();
    }

    /** Σ(debit − credit) per account within a period. @return array<int,float> */
    protected function movements(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return JournalLine::query()->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->selectRaw('account_id, SUM(debit) - SUM(credit) as net')->groupBy('account_id')->toBase()->pluck('net', 'account_id')
            ->map(fn ($v) => (float) $v)->all();
    }

    protected function flowLabel(?Account $account): string
    {
        if (! $account) {
            return __('Other');
        }

        return match ($account->system_key) {
            'fees_receivable', 'student_advance' => __('Student fees'),
            'salary_payable', 'salaries' => __('Salaries'),
            'salary_deductions' => __('PF, tax and other deductions'),
            'payable' => __('Suppliers'),
            default => $account->name,
        };
    }
}
