<?php

namespace App\Services\Reports\Definitions\Library;

use App\Models\Library\LibraryTransaction;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Loans that are past their due date today, longest overdue first. */
class OverdueBooksReport extends Report
{
    use Lookups;

    public function key(): string { return 'library.overdue'; }

    public function title(): string { return __('Overdue books'); }

    public function description(): string { return __('Books not yet returned after their due date, with the member to chase.'); }

    public function permission(): string { return 'library.view'; }

    public function filters(): array
    {
        return [['name' => 'member_type', 'type' => 'select', 'label' => 'Member type', 'options' => ['student' => __('Students'), 'employee' => __('Staff')]],
            ['name' => 'min_days', 'type' => 'number', 'label' => 'At least this many days late', 'default' => 1]];
    }

    public function run(array $filters): R
    {
        $today = CarbonImmutable::today();
        $min = max((int) ($filters['min_days'] ?? 1), 1);
        $loans = LibraryTransaction::query()->open()->with(['book:id,title,title_bn', 'copy:id,accession_no'])
            ->whereDate('due_on', '<=', $today->subDays($min)->toDateString())
            ->when($filters['member_type'] ?? null, fn ($q, $v) => $q->where('member_type', $v))
            ->orderBy('due_on')->get();
        $members = $this->members($loans);

        $rows = $loans->map(function (LibraryTransaction $t) use ($members, $today) {
            $m = $members[$t->member_type.':'.$t->member_id] ?? ['name' => '—', 'code' => null, 'type' => ucfirst($t->member_type)];

            return ['member' => $m['name'], 'code' => $m['code'], 'type' => $m['type'], 'accession' => $t->copy?->accession_no, 'title' => $t->book?->displayTitle(),
                'issued' => $t->issued_on, 'due' => $t->due_on, 'late' => (int) $t->due_on->diffInDays($today)];
        })->all();

        $r = $this->result([R::col('member', __('Member')), R::col('code', __('ID')), R::col('type', __('Type')), R::col('accession', __('Accession no')), R::col('title', __('Title')),
            R::col('issued', __('Issued'), 'date'), R::col('due', __('Due'), 'date'), R::col('late', __('Days late'), 'int')], $rows);
        $r->subtitle = __('As of :date', ['date' => $today->format('d/m/Y')]);

        return $r;
    }
}
