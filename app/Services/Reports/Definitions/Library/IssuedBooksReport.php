<?php

namespace App\Services\Reports\Definitions\Library;

use App\Models\Library\LibraryTransaction;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Every loan issued in a period with its member, due date and return. */
class IssuedBooksReport extends Report
{
    use Lookups;

    public function key(): string { return 'library.issued'; }

    public function title(): string { return __('Books issued'); }

    public function description(): string { return __('Loans issued in a period: who has which book, due and returned dates.'); }

    public function permission(): string { return 'library.view'; }

    public function filters(): array
    {
        return [...$this->periodFilters(),
            ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => ['issued' => __('Still out'), 'returned' => __('Returned'), 'lost' => __('Lost')]],
            ['name' => 'member_type', 'type' => 'select', 'label' => 'Member type', 'options' => ['student' => __('Students'), 'employee' => __('Staff')]]];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $today = CarbonImmutable::today();
        $loans = LibraryTransaction::query()->with(['book:id,title,title_bn', 'copy:id,accession_no'])
            ->whereDate('issued_on', '>=', $from->toDateString())->whereDate('issued_on', '<=', $to->toDateString())
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['member_type'] ?? null, fn ($q, $v) => $q->where('member_type', $v))
            ->orderBy('issued_on')->orderBy('id')->get();
        $members = $this->members($loans);

        $rows = $loans->map(function (LibraryTransaction $t) use ($members, $today) {
            $m = $members[$t->member_type.':'.$t->member_id] ?? ['name' => '—', 'code' => null, 'type' => ucfirst($t->member_type)];
            $end = $t->returned_on ?? $today;
            $late = $t->due_on && $end->gt($t->due_on) ? (int) $t->due_on->diffInDays($end) : 0;

            return ['accession' => $t->copy?->accession_no, 'title' => $t->book?->displayTitle(), 'member' => $m['name'], 'code' => $m['code'], 'type' => $m['type'],
                'issued' => $t->issued_on, 'due' => $t->due_on, 'returned' => $t->returned_on, 'late' => $t->status === 'lost' ? null : $late, 'status' => __(ucfirst($t->status)), 'renewals' => $t->renewals];
        })->all();

        return $this->result([R::col('accession', __('Accession no')), R::col('title', __('Title')), R::col('member', __('Member')), R::col('code', __('ID')), R::col('type', __('Type')),
            R::col('issued', __('Issued'), 'date'), R::col('due', __('Due'), 'date'), R::col('returned', __('Returned'), 'date'), R::col('late', __('Days late'), 'int'),
            R::col('status', __('Status')), R::col('renewals', __('Renewals'), 'int')], $rows);
    }
}
