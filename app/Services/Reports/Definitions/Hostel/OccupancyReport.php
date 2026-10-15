<?php

namespace App\Services\Reports\Definitions\Hostel;

use App\Models\Hostel\Allocation;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\Room;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Beds, occupied and vacant beds of every room. */
class OccupancyReport extends Report
{
    public function key(): string { return 'hostel.occupancy'; }

    public function title(): string { return __('Hostel occupancy'); }

    public function description(): string { return __('Beds, occupied and vacant per room, with the occupancy rate.'); }

    public function permission(): string { return 'hostel.view'; }

    public function filters(): array
    {
        return [['name' => 'hostel', 'type' => 'select', 'label' => 'Hostel', 'options' => fn () => Hostel::query()->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'vacant', 'type' => 'bool', 'label' => 'Only rooms with a vacant bed']];
    }

    public function run(array $filters): R
    {
        $taken = Allocation::query()->toBase()->where('status', 'active')->selectRaw('hostel_room_id, COUNT(*) as n')->groupBy('hostel_room_id')->pluck('n', 'hostel_room_id');
        $rooms = Room::query()->with('hostel:id,name,type')->withCount(['beds as beds_count' => fn ($q) => $q->where('is_active', true)])->where('is_active', true)
            ->when($filters['hostel'] ?? null, fn ($q, $v) => $q->where('hostel_id', $v))->get()
            ->sortBy(fn (Room $r) => sprintf('%s-%s-%s', $r->hostel?->name, $r->building, str_pad((string) $r->room_no, 8, '0', STR_PAD_LEFT)))->values();

        $rows = [];
        foreach ($rooms as $room) {
            $occupied = (int) ($taken[$room->id] ?? 0);
            $vacant = max($room->beds_count - $occupied, 0);
            if (! empty($filters['vacant']) && $vacant === 0) {
                continue;
            }
            $rows[] = ['hostel' => $room->hostel?->name, 'type' => __(ucfirst((string) $room->hostel?->type)), 'room' => trim(($room->building ? $room->building.' · ' : '').$room->room_no), 'floor' => $room->floor,
                'kind' => __(ucfirst($room->type)), 'beds' => $room->beds_count, 'occupied' => $occupied, 'vacant' => $vacant, 'rate' => $room->beds_count ? round($occupied / $room->beds_count * 100, 1) : 0, 'fee' => (float) $room->monthly_fee];
        }

        $r = $this->result([R::col('hostel', __('Hostel')), R::col('type', __('For')), R::col('room', __('Room')), R::col('floor', __('Floor')), R::col('kind', __('Room type')), R::col('beds', __('Beds'), 'int', true),
            R::col('occupied', __('Occupied'), 'int', true), R::col('vacant', __('Vacant'), 'int', true), R::col('rate', __('Occupancy'), 'percent'), R::col('fee', __('Fee per bed'), 'money')], $rows);
        $beds = array_sum(array_column($rows, 'beds'));
        $r->summary = $beds ? [__('Occupancy') => round(array_sum(array_column($rows, 'occupied')) / $beds * 100, 1).'%'] : [];

        return $r;
    }
}
