<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Services\Finance\FeeReportService;
use App\Services\Reports\ReportResult as R;

/** Discounts on invoice lines and waived balances in a period. */
class DiscountsReport extends FinanceReport
{
    public function __construct(protected FeeReportService $service) {}

    public function key(): string { return 'fees.discounts'; }

    public function title(): string { return __('Discounts and waivers'); }

    public function description(): string { return __('Fee discounts given on invoice lines and balances waived in a period.'); }

    public function filters(): array
    {
        return $this->periodFilters();
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $data = $this->service->discounts($from, $to);
        $rows = [];

        foreach ($data['items'] as $item) {
            $s = $data['students']->get($item->student_id);
            $rows[] = ['date' => $item->issue_date, 'invoice' => $item->invoice_no, 'code' => $s?->student_code, 'student' => $s?->name, 'kind' => __('Discount'), 'description' => $item->description,
                'note' => $item->discount_note, 'amount' => (float) $item->amount, 'discount' => (float) $item->discount];
        }
        foreach ($data['waivers'] as $inv) {
            $rows[] = ['date' => $inv->issue_date, 'invoice' => $inv->invoice_no, 'code' => $inv->student?->student_code, 'student' => $inv->student?->name, 'kind' => __('Waiver'), 'description' => null,
                'note' => null, 'amount' => null, 'discount' => (float) $inv->waived];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));

        return $this->result([R::col('date', __('Invoice date'), 'date'), R::col('invoice', __('Invoice')), R::col('code', __('Student ID')), R::col('student', __('Student')), R::col('kind', __('Kind')),
            R::col('description', __('Fee')), R::col('note', __('Reason')), R::col('amount', __('Billed'), 'money'), R::col('discount', __('Discount / waived'), 'money', true)], $rows);
    }
}
