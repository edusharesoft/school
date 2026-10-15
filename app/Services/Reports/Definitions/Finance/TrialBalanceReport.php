<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Services\Finance\AccountingReportService;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Debit and credit balance of every posting account at a date. */
class TrialBalanceReport extends FinanceReport
{
    public function __construct(protected AccountingReportService $service) {}

    public function key(): string { return 'accounts.trial-balance'; }

    public function title(): string { return __('Trial balance'); }

    public function description(): string { return __('Debit and credit balances of all accounts at a date.'); }

    public function filters(): array
    {
        return [['name' => 'as_of', 'type' => 'date', 'label' => 'As of', 'default' => $this->toDefault()]];
    }

    public function run(array $filters): R
    {
        $asOf = CarbonImmutable::parse($filters['as_of'] ?? now()->toDateString());
        $data = $this->service->trialBalance($asOf);
        $rows = $data['rows']->map(fn ($r) => ['code' => $r['account']->code, 'account' => $r['account']->name, 'type' => __(ucfirst($r['account']->type)), 'debit' => $r['debit'], 'credit' => $r['credit']])->all();

        return $this->result([R::col('code', __('Code')), R::col('account', __('Account')), R::col('type', __('Type')), R::col('debit', __('Debit'), 'money', true), R::col('credit', __('Credit'), 'money', true)], $rows);
    }
}
