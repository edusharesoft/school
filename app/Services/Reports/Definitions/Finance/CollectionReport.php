<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\FeePayment;
use App\Services\Finance\FeeReportService;
use App\Services\Reports\ReportResult as R;

/** Fee receipts of a period, with refunds listed as negative rows. */
class CollectionReport extends FinanceReport
{
    public function __construct(protected FeeReportService $service) {}

    public function key(): string { return 'fees.collection'; }

    public function title(): string { return __('Fee collection'); }

    public function description(): string { return __('Receipts of a period by date, method and collector; refunds are shown as negative amounts.'); }

    public function filters(): array
    {
        return [...$this->periodFilters(),
            ['name' => 'method', 'type' => 'select', 'label' => 'Method', 'options' => array_combine(FeePayment::METHODS, array_map(fn ($m) => __(ucfirst($m)), FeePayment::METHODS))],
            ['name' => 'account_id', 'type' => 'select', 'label' => 'Account', 'options' => fn () => Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all()]];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $data = $this->service->collection($from, $to, array_filter(['method' => $filters['method'] ?? null, 'account_id' => $filters['account_id'] ?? null]));

        $rows = $data['payments']->map(fn ($p) => ['date' => $p->date, 'receipt' => $p->receipt_no, 'code' => $p->student?->student_code, 'student' => $p->student?->name, 'method' => $p->method,
            'account' => $p->account?->name, 'reference' => $p->reference, 'by' => $p->receiver?->name, 'amount' => (float) $p->amount]);
        foreach ($data['refunds'] as $r) {
            $rows->push(['date' => $r->date, 'receipt' => __('Refund'), 'code' => $r->student?->student_code, 'student' => $r->student?->name, 'method' => null, 'account' => null,
                'reference' => $r->reason ?? null, 'by' => null, 'amount' => -(float) $r->amount]);
        }

        $result = $this->result([R::col('date', __('Date'), 'date'), R::col('receipt', __('Receipt')), R::col('code', __('Student ID')), R::col('student', __('Student')), R::col('method', __('Method')),
            R::col('account', __('Account')), R::col('reference', __('Reference')), R::col('by', __('Collected by')), R::col('amount', __('Amount'), 'money', true)], $rows->sortBy('date')->values()->all());
        $result->summary = [__('Collected') => $data['total'], __('Refunded') => $data['refunded'], __('Net') => $data['net']];

        return $result;
    }
}
