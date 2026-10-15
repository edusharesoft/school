<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\Budget;
use App\Models\Finance\FinancialYear;
use App\Models\Finance\JournalLine;
use App\Services\Finance\AccountingReportService;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Ledger, trial balance, income & expenditure, balance sheet, cash flow, budgets, bank reconciliation, financial years. */
class AccountingReportController extends Controller
{
    public function __construct(protected AccountingReportService $reports, protected LedgerService $ledger) {}

    public function ledger(Request $request): View
    {
        $this->authorize('financial-reports.view');
        app(ChartOfAccounts::class)->ensure();
        [$from, $to] = $this->range($request);
        $accounts = Account::query()->where('is_group', false)->orderBy('code')->get();
        $account = $accounts->firstWhere('id', $request->integer('account_id')) ?? $accounts->firstWhere('system_key', 'cash');

        return view('branch.finance.reports.ledger', ['accounts' => $accounts, 'from' => $from, 'to' => $to]
            + ($account ? $this->reports->generalLedger($account, $from, $to) : ['account' => null]));
    }

    public function trialBalance(Request $request): View
    {
        $this->authorize('financial-reports.view');
        $asOf = $request->date('as_of') ? CarbonImmutable::parse($request->date('as_of')) : CarbonImmutable::today();

        return view('branch.finance.reports.trial-balance', $this->reports->trialBalance($asOf) + ['asOf' => $asOf]);
    }

    public function incomeExpenditure(Request $request): View
    {
        $this->authorize('financial-reports.view');
        [$from, $to] = $this->range($request, true);

        return view('branch.finance.reports.income-expenditure', $this->reports->incomeExpenditure($from, $to) + ['from' => $from, 'to' => $to]);
    }

    public function balanceSheet(Request $request): View
    {
        $this->authorize('financial-reports.view');
        $asOf = $request->date('as_of') ? CarbonImmutable::parse($request->date('as_of')) : CarbonImmutable::today();

        return view('branch.finance.reports.balance-sheet', $this->reports->balanceSheet($asOf) + ['asOf' => $asOf]);
    }

    public function cashFlow(Request $request): View
    {
        $this->authorize('financial-reports.view');
        [$from, $to] = $this->range($request, true);

        return view('branch.finance.reports.cash-flow', $this->reports->cashFlow($from, $to) + ['from' => $from, 'to' => $to]);
    }

    public function budget(Request $request): View
    {
        $this->authorize('financial-reports.view');
        $current = $this->ledger->yearOf(now());
        $years = FinancialYear::query()->orderByDesc('starts_on')->get();
        $year = $years->firstWhere('id', $request->integer('financial_year_id')) ?? $current;

        return view('branch.finance.reports.budget', ['years' => $years, 'year' => $year, 'rows' => $this->reports->budgetVsActual($year)]);
    }

    public function saveBudget(Request $request): RedirectResponse
    {
        $this->authorize('accounting.post');
        $data = $request->validate([
            'financial_year_id' => ['required', 'integer'],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
        ]);
        $year = FinancialYear::query()->findOrFail($data['financial_year_id']);
        $accounts = Account::query()->whereIn('type', ['income', 'expense'])->where('is_group', false)->pluck('id')->flip();

        DB::transaction(function () use ($data, $year, $accounts) {
            foreach ($data['amounts'] ?? [] as $accountId => $amount) {
                if (! $accounts->has((int) $accountId)) {
                    continue;
                }
                if ((float) $amount > 0) {
                    Budget::query()->updateOrCreate(['financial_year_id' => $year->id, 'account_id' => (int) $accountId], ['amount' => round((float) $amount, 2)]);
                } else {
                    Budget::query()->where('financial_year_id', $year->id)->where('account_id', (int) $accountId)->delete();
                }
            }
        });

        return back()->with('success', __('Budget saved.'));
    }

    /** Bank reconciliation: tick the book lines that appear on the bank statement. */
    public function reconcile(Request $request): View
    {
        $this->authorize('accounting.view');
        $banks = Account::query()->money()->whereIn('kind', ['bank', 'mobile'])->get();
        $account = $banks->firstWhere('id', $request->integer('account_id')) ?? $banks->first();
        $asOf = $request->date('as_of') ? CarbonImmutable::parse($request->date('as_of')) : CarbonImmutable::today();

        $lines = $account ? JournalLine::query()->with('entry:id,entry_no,narration')->where('account_id', $account->id)
            ->whereDate('date', '<=', $asOf->toDateString())
            ->where(fn ($q) => $q->whereNull('reconciled_on')->orWhereDate('reconciled_on', '>', $asOf->toDateString()))
            ->orderBy('date')->orderBy('id')->get() : collect();
        $book = $account ? $this->ledger->balance($account, $asOf) : 0.0;
        $uncleared = round($lines->sum(fn ($l) => $l->debit - $l->credit), 2);

        return view('branch.finance.reports.reconcile', [
            'banks' => $banks, 'account' => $account, 'asOf' => $asOf, 'lines' => $lines, 'book' => $book,
            'cleared' => round($book - $uncleared, 2), 'statement' => $request->input('statement'),
        ]);
    }

    public function saveReconcile(Request $request): RedirectResponse
    {
        $this->authorize('accounting.post');
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'as_of' => ['required', 'date'],
            'line_ids' => ['nullable', 'array'],
            'line_ids.*' => ['integer'],
            'statement' => ['nullable', 'numeric'],
        ]);
        $account = Account::query()->money()->findOrFail($data['account_id']);
        $count = JournalLine::query()->where('account_id', $account->id)->whereIn('id', $data['line_ids'] ?? [])->whereNull('reconciled_on')
            ->whereDate('date', '<=', $data['as_of'])->update(['reconciled_on' => $data['as_of'], 'reconciled_by' => $request->user()->id]);

        return redirect()->route('branch.finance.reports.reconcile', ['account_id' => $account->id, 'as_of' => $data['as_of'], 'statement' => $data['statement'] ?? null])
            ->with('success', __(':count line(s) marked as cleared.', ['count' => $count]));
    }

    public function years(): View
    {
        $this->authorize('accounting.view');
        $this->ledger->yearOf(now()); // make sure the current year exists

        return view('branch.finance.reports.years', ['years' => FinancialYear::query()->orderByDesc('starts_on')->get()]);
    }

    public function toggleYear(Request $request, FinancialYear $year): RedirectResponse
    {
        $this->authorize($year->is_closed ? 'accounting.reverse' : 'accounting.post');
        $year->forceFill(['is_closed' => ! $year->is_closed, 'closed_at' => $year->is_closed ? null : now(), 'closed_by' => $year->is_closed ? null : $request->user()->id])->save();

        return back()->with('success', $year->is_closed ? __('Financial year :year closed. No more entries can be dated in it.', ['year' => $year->name]) : __('Financial year :year reopened.', ['year' => $year->name]));
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    protected function range(Request $request, bool $yearDefault = false): array
    {
        $default = $yearDefault ? CarbonImmutable::parse($this->ledger->yearOf(now())->starts_on) : CarbonImmutable::today()->startOfMonth();
        $from = $request->date('from') ? CarbonImmutable::parse($request->date('from')) : $default;
        $to = $request->date('to') ? CarbonImmutable::parse($request->date('to')) : CarbonImmutable::today();

        return $to->lt($from) ? [$to->startOfDay(), $from->startOfDay()] : [$from->startOfDay(), $to->startOfDay()];
    }
}
