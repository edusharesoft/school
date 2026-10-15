<?php

namespace App\Services\Finance;

use App\Models\Branch;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Finance\JournalLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Institution-level (central area) figures: every branch's books side by side plus the total.
 * Runs in the central context, so BranchScope limits rows to the signed-in user's institution.
 */
class ConsolidatedReportService
{
    /** @param int[] $branchIds */
    public function summary(array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $branches = Branch::query()->whereIn('id', $branchIds)->ordered()->get(['id', 'name', 'code']);

        $byType = JournalLine::query()->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('journal_lines.branch_id', $branchIds)->whereIn('accounts.type', ['income', 'expense'])
            ->whereDate('journal_lines.date', '>=', $from->toDateString())->whereDate('journal_lines.date', '<=', $to->toDateString())
            ->selectRaw('journal_lines.branch_id, accounts.type, SUM(journal_lines.debit) - SUM(journal_lines.credit) as net')
            ->groupBy('journal_lines.branch_id', 'accounts.type')->toBase()->get();

        $cash = JournalLine::query()->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('journal_lines.branch_id', $branchIds)->whereNotNull('accounts.kind')
            ->whereDate('journal_lines.date', '<=', $to->toDateString())
            ->selectRaw('journal_lines.branch_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) as net')->groupBy('journal_lines.branch_id')
            ->toBase()->pluck('net', 'branch_id');

        $collected = FeePayment::query()->whereIn('branch_id', $branchIds)->where('status', 'completed')
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->selectRaw('branch_id, SUM(amount) as total')->groupBy('branch_id')->toBase()->pluck('total', 'branch_id');
        $outstanding = FeeInvoice::query()->whereIn('branch_id', $branchIds)->whereIn('status', ['unpaid', 'partial'])
            ->selectRaw('branch_id, SUM(balance) as total')->groupBy('branch_id')->toBase()->pluck('total', 'branch_id');

        $rows = $branches->map(function (Branch $b) use ($byType, $cash, $collected, $outstanding) {
            $income = round(-(float) ($byType->first(fn ($r) => (int) $r->branch_id === $b->id && $r->type === 'income')?->net ?? 0), 2);
            $expense = round((float) ($byType->first(fn ($r) => (int) $r->branch_id === $b->id && $r->type === 'expense')?->net ?? 0), 2);

            return [
                'branch' => $b, 'income' => $income, 'expense' => $expense, 'surplus' => round($income - $expense, 2),
                'collected' => round((float) ($collected[$b->id] ?? 0), 2), 'outstanding' => round((float) ($outstanding[$b->id] ?? 0), 2),
                'cash' => round((float) ($cash[$b->id] ?? 0), 2),
            ];
        });

        $total = [];
        foreach (['income', 'expense', 'surplus', 'collected', 'outstanding', 'cash'] as $k) {
            $total[$k] = round($rows->sum($k), 2);
        }

        return ['rows' => $rows, 'total' => $total, 'lines' => $this->incomeExpenditureLines($branchIds, $from, $to)];
    }

    /** Consolidated income & expenditure by account (branches share the default chart codes). */
    protected function incomeExpenditureLines(array $branchIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return JournalLine::query()->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('journal_lines.branch_id', $branchIds)->whereIn('accounts.type', ['income', 'expense'])
            ->whereDate('journal_lines.date', '>=', $from->toDateString())->whereDate('journal_lines.date', '<=', $to->toDateString())
            ->selectRaw('accounts.type, accounts.code, accounts.name, SUM(journal_lines.debit) - SUM(journal_lines.credit) as net')
            ->groupBy('accounts.type', 'accounts.code', 'accounts.name')->orderBy('accounts.code')->toBase()->get()
            ->map(fn ($r) => ['type' => $r->type, 'code' => $r->code, 'name' => $r->name, 'amount' => round(($r->type === 'income' ? -1 : 1) * (float) $r->net, 2)])
            ->filter(fn ($r) => $r['amount'] != 0)->values();
    }
}
