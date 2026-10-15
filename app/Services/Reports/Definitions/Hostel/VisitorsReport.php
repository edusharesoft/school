<?php

namespace App\Services\Reports\Definitions\Hostel;

use App\Models\Hostel\Hostel;
use App\Models\Hostel\Visitor;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Visitor log of the hostels. */
class VisitorsReport extends Report
{
    public function key(): string { return 'hostel.visitors'; }

    public function title(): string { return __('Hostel visitors'); }

    public function description(): string { return __('Who visited which resident, when they came in and left.'); }

    public function permission(): string { return 'hostel.view'; }

    public function filters(): array
    {
        return [...$this->periodFilters(), ['name' => 'hostel', 'type' => 'select', 'label' => 'Hostel', 'options' => fn () => Hostel::query()->orderBy('name')->pluck('name', 'id')->all()]];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $rows = Visitor::query()->with(['student:id,name,student_code', 'hostel:id,name'])
            ->whereDate('in_at', '>=', $from->toDateString())->whereDate('in_at', '<=', $to->toDateString())
            ->when($filters['hostel'] ?? null, fn ($q, $v) => $q->where('hostel_id', $v))->orderBy('in_at')->get()
            ->map(fn (Visitor $v) => ['in' => $v->in_at, 'out' => $v->out_at, 'hostel' => $v->hostel?->name, 'visitor' => $v->visitor_name, 'relation' => $v->relation, 'phone' => $v->phone,
                'code' => $v->student?->student_code, 'student' => $v->student?->name, 'purpose' => $v->purpose,
                'minutes' => $v->out_at ? (int) $v->in_at->diffInMinutes($v->out_at) : null])->all();

        return $this->result([R::col('in', __('In'), 'datetime'), R::col('out', __('Out'), 'datetime'), R::col('minutes', __('Minutes'), 'int'), R::col('hostel', __('Hostel')), R::col('visitor', __('Visitor')),
            R::col('relation', __('Relation')), R::col('phone', __('Phone')), R::col('code', __('Student ID')), R::col('student', __('Resident')), R::col('purpose', __('Purpose'))], $rows);
    }
}
