<?php

namespace App\Services\Reports\Definitions\Transport;

use App\Models\Transport\Allocation;
use App\Models\Transport\TransportRoute;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Students riding each route and stop with the fee they pay. */
class RouteRosterReport extends Report
{
    public function key(): string { return 'transport.roster'; }

    public function title(): string { return __('Transport roster'); }

    public function description(): string { return __('Students on each route and stop, with pick-up time, contact and monthly fee.'); }

    public function permission(): string { return 'transport.view'; }

    public function filters(): array
    {
        return [['name' => 'route', 'type' => 'select', 'label' => 'Route', 'options' => fn () => TransportRoute::query()->orderBy('code')->get()->mapWithKeys(fn ($r) => [$r->id => $r->code.' — '.$r->name])->all()]];
    }

    public function run(array $filters): R
    {
        $allocations = Allocation::query()->where('status', 'active')
            ->with(['student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name', 'route:id,code,name', 'stop:id,name,sequence,pickup_time,drop_time'])
            ->when($filters['route'] ?? null, fn ($q, $v) => $q->where('transport_route_id', $v))->get()
            ->sortBy(fn (Allocation $a) => sprintf('%s-%05d-%s', $a->route?->code, $a->stop?->sequence ?? 0, $a->student?->name))->values();

        $rows = $allocations->map(fn (Allocation $a) => [
            'route' => trim(($a->route?->code ?? '').' '.($a->route?->name ?? '')), 'stop' => $a->stop?->name, 'pickup' => $a->stop?->pickup_time ? substr($a->stop->pickup_time, 0, 5) : null,
            'drop' => $a->stop?->drop_time ? substr($a->stop->drop_time, 0, 5) : null, 'code' => $a->student?->student_code, 'student' => $a->student?->name,
            'class' => $a->student?->currentEnrollment?->schoolClass?->name, 'section' => $a->student?->currentEnrollment?->section?->name, 'phone' => $a->student?->phone,
            'since' => $a->starts_on, 'fee' => (float) $a->monthly_fee,
        ])->all();

        return $this->result([R::col('route', __('Route')), R::col('stop', __('Stop')), R::col('pickup', __('Pick-up')), R::col('drop', __('Drop')), R::col('code', __('Student ID')), R::col('student', __('Student')),
            R::col('class', __('Class')), R::col('section', __('Section')), R::col('phone', __('Phone')), R::col('since', __('Since'), 'date'), R::col('fee', __('Monthly fee'), 'money', true)], $rows);
    }
}
