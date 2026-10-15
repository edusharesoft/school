<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Models\Academic\Section;
use App\Services\Finance\FeeReportService;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\ReportResult as R;
use Carbon\CarbonImmutable;

/** Outstanding fees per student with age buckets. */
class DuesReport extends FinanceReport
{
    use Lookups;

    public function __construct(protected FeeReportService $service) {}

    public function key(): string { return 'fees.dues'; }

    public function title(): string { return __('Outstanding dues'); }

    public function description(): string { return __('Unpaid fees per student, aged into not yet due, 1-30, 31-60, 61-90 and over 90 days.'); }

    public function filters(): array
    {
        return [['name' => 'class_id', 'type' => 'select', 'label' => 'Class', 'options' => fn () => $this->classOptions()],
            ['name' => 'section_id', 'type' => 'select', 'label' => 'Section', 'options' => fn () => Section::query()->with('schoolClass:id,name,numeric_order')->get()->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->mapWithKeys(fn ($s) => [$s->id => trim(($s->schoolClass?->name ?? '').' — '.$s->name)])->all()],
            ['name' => 'overdue', 'type' => 'bool', 'label' => 'Only overdue invoices']];
    }

    public function run(array $filters): R
    {
        $rows = $this->service->dues(['class_id' => $filters['class_id'] ?? null, 'section_id' => $filters['section_id'] ?? null, 'overdue' => ! empty($filters['overdue'])])
            ->map(fn ($r) => ['code' => $r['student']?->student_code, 'student' => $r['student']?->name, 'phone' => $r['student']?->phone, 'class' => $r['class'], 'section' => $r['section'],
                'invoices' => $r['invoices'], 'oldest' => $r['oldest_due'], 'current' => $r['buckets']['current'], 'b1' => $r['buckets']['1_30'], 'b2' => $r['buckets']['31_60'],
                'b3' => $r['buckets']['61_90'], 'b4' => $r['buckets']['90_plus'], 'balance' => $r['balance']])->all();

        $result = $this->result([R::col('code', __('Student ID')), R::col('student', __('Student')), R::col('phone', __('Mobile')), R::col('class', __('Class')), R::col('section', __('Section')),
            R::col('invoices', __('Invoices'), 'int', true), R::col('oldest', __('Oldest due'), 'date'), R::col('current', __('Not yet due'), 'money', true), R::col('b1', '1-30', 'money', true),
            R::col('b2', '31-60', 'money', true), R::col('b3', '61-90', 'money', true), R::col('b4', '90+', 'money', true), R::col('balance', __('Total due'), 'money', true)], $rows);
        $result->subtitle = __('As of :date', ['date' => CarbonImmutable::today()->format('d/m/Y')]);

        return $result;
    }
}
