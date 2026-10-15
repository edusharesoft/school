<?php

namespace App\Services\Reports\Definitions\Transport;

use App\Models\Transport\FuelLog;
use App\Models\Transport\Maintenance;
use App\Models\Transport\Vehicle;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Illuminate\Support\Facades\DB;

/** Fuel and maintenance cost per vehicle (recorded for reporting; not posted to the accounts). */
class VehicleCostReport extends Report
{
    public function key(): string { return 'transport.costs'; }

    public function title(): string { return __('Vehicle running costs'); }

    public function description(): string { return __('Fuel and maintenance spending of each vehicle in a period.'); }

    public function permission(): string { return 'transport.view'; }

    public function filters(): array
    {
        return $this->periodFilters();
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $fuel = FuelLog::query()->toBase()->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->select('transport_vehicle_id', DB::raw('SUM(litres) as litres'), DB::raw('SUM(total_cost) as cost'), DB::raw('COUNT(*) as fills'))->groupBy('transport_vehicle_id')->get()->keyBy('transport_vehicle_id');
        $service = Maintenance::query()->toBase()->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->select('transport_vehicle_id', DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as jobs'))->groupBy('transport_vehicle_id')->get()->keyBy('transport_vehicle_id');

        $rows = Vehicle::query()->orderBy('registration_no')->get()->map(function (Vehicle $v) use ($fuel, $service) {
            $f = $fuel->get($v->id);
            $m = $service->get($v->id);
            $fuelCost = round((float) ($f->cost ?? 0), 2);
            $serviceCost = round((float) ($m->cost ?? 0), 2);

            return ['vehicle' => $v->registration_no.($v->name ? ' ('.$v->name.')' : ''), 'type' => __(ucfirst($v->type)), 'fills' => (int) ($f->fills ?? 0), 'litres' => round((float) ($f->litres ?? 0), 2),
                'fuel' => $fuelCost, 'jobs' => (int) ($m->jobs ?? 0), 'maintenance' => $serviceCost, 'total' => round($fuelCost + $serviceCost, 2)];
        })->filter(fn ($r) => $r['total'] > 0 || $r['fills'] > 0 || $r['jobs'] > 0)->values()->all();

        return $this->result([R::col('vehicle', __('Vehicle')), R::col('type', __('Type')), R::col('fills', __('Fuel entries'), 'int', true), R::col('litres', __('Litres'), 'number', true),
            R::col('fuel', __('Fuel cost'), 'money', true), R::col('jobs', __('Maintenance jobs'), 'int', true), R::col('maintenance', __('Maintenance cost'), 'money', true), R::col('total', __('Total'), 'money', true)], $rows);
    }
}
