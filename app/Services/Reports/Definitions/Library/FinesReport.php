<?php

namespace App\Services\Reports\Definitions\Library;

use App\Models\Library\LibraryFine;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Library fines raised in a period: overdue, lost and damaged books with what was paid, waived or is still owed. */
class FinesReport extends Report
{
    use Lookups;

    public function key(): string { return 'library.fines'; }

    public function title(): string { return __('Library fines'); }

    public function description(): string { return __('Fines raised in a period with their status, receipt and who owes what.'); }

    public function permission(): string { return 'library.view'; }

    public function filters(): array
    {
        return [...$this->periodFilters(),
            ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => ['unpaid' => __('Unpaid'), 'paid' => __('Paid'), 'waived' => __('Waived')]],
            ['name' => 'kind', 'type' => 'select', 'label' => 'Kind', 'options' => ['overdue' => __('Overdue'), 'lost' => __('Lost'), 'damaged' => __('Damaged')]]];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $fines = LibraryFine::query()->with('transaction.book:id,title,title_bn')
            ->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->orderBy('created_at')->get();
        $members = $this->members($fines);

        $rows = $fines->map(function (LibraryFine $f) use ($members) {
            $m = $members[$f->member_type.':'.$f->member_id] ?? ['name' => '—', 'code' => null, 'type' => ucfirst($f->member_type)];

            return ['date' => $f->created_at, 'member' => $m['name'], 'code' => $m['code'], 'type' => $m['type'], 'book' => $f->transaction?->book?->displayTitle(), 'kind' => __(ucfirst($f->kind)),
                'amount' => (float) $f->amount, 'paid' => $f->status === 'paid' ? (float) $f->amount : 0, 'waived' => $f->status === 'waived' ? (float) $f->amount : 0,
                'owed' => $f->status === 'unpaid' ? (float) $f->amount : 0, 'status' => __(ucfirst($f->status)), 'receipt' => $f->receipt_no, 'paid_on' => $f->paid_on];
        })->all();

        return $this->result([R::col('date', __('Date'), 'date'), R::col('member', __('Member')), R::col('code', __('ID')), R::col('type', __('Type')), R::col('book', __('Book')), R::col('kind', __('Kind')),
            R::col('amount', __('Fine'), 'money', true), R::col('paid', __('Paid'), 'money', true), R::col('waived', __('Waived'), 'money', true), R::col('owed', __('Owed'), 'money', true),
            R::col('status', __('Status')), R::col('receipt', __('Receipt')), R::col('paid_on', __('Paid on'), 'date')], $rows);
    }
}
