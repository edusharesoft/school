<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Services\Finance\AccountingReportService;
use App\Services\Reports\ReportResult as R;

/** Income and expenditure of a period with the surplus. */
class IncomeExpenditureReport extends FinanceReport
{
    public function __construct(protected AccountingReportService $service) {}

    public function key(): string { return 'accounts.income-expenditure'; }

    public function title(): string { return __('Income and expenditure'); }

    public function description(): string { return __('Income and expense accounts for a period and the resulting surplus or deficit.'); }

    public function filters(): array
    {
        return $this->periodFilters();
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $data = $this->service->incomeExpenditure($from, $to);
        $rows = [];
        foreach ($data['income'] as $r) {
            $rows[] = ['side' => __('Income'), 'code' => $r['account']->code, 'account' => $r['account']->name, 'amount' => $r['amount']];
        }
        foreach ($data['expense'] as $r) {
            $rows[] = ['side' => __('Expenditure'), 'code' => $r['account']->code, 'account' => $r['account']->name, 'amount' => $r['amount']];
        }

        $result = $this->result([R::col('side', __('Section')), R::col('code', __('Code')), R::col('account', __('Account')), R::col('amount', __('Amount'), 'money')], $rows);
        $result->summary = [__('Total income') => $data['totalIncome'], __('Total expenditure') => $data['totalExpense'], __('Surplus / (deficit)') => $data['surplus']];

        return $result;
    }
}
