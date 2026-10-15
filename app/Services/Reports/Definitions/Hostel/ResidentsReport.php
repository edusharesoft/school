<?php

namespace App\Services\Reports\Definitions\Hostel;

use App\Models\Hostel\Allocation;
use App\Models\Hostel\Hostel;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Students living in the hostels now, with bed, meal plan and monthly charge. */
class ResidentsReport extends Report
{
    public function key(): string { return 'hostel.residents'; }

    public function title(): string { return __('Hostel residents'); }

    public function description(): string { return __('Who lives in which room and bed, their meal plan and monthly hostel charge.'); }

    public function permission(): string { return 'hostel.view'; }

    public function filters(): array
    {
        return [['name' => 'hostel', 'type' => 'select', 'label' => 'Hostel', 'options' => fn () => Hostel::query()->orderBy('name')->pluck('name', 'id')->all()]];
    }

    public function run(array $filters): R
    {
        $rows = Allocation::query()->where('status', 'active')
            ->with(['student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name', 'hostel:id,name', 'room:id,room_no,building,floor', 'bed:id,label', 'mealPlan:id,name'])
            ->when($filters['hostel'] ?? null, fn ($q, $v) => $q->where('hostel_id', $v))->get()
            ->sortBy(fn (Allocation $a) => sprintf('%s-%s-%s-%s', $a->hostel?->name, $a->room?->building, $a->room?->room_no, $a->bed?->label))->values()
            ->map(fn (Allocation $a) => ['hostel' => $a->hostel?->name, 'room' => trim(($a->room?->building ? $a->room->building.' · ' : '').$a->room?->room_no), 'bed' => $a->bed?->label,
                'code' => $a->student?->student_code, 'student' => $a->student?->name, 'class' => $a->student?->currentEnrollment?->schoolClass?->name, 'section' => $a->student?->currentEnrollment?->section?->name,
                'phone' => $a->student?->phone, 'meal' => $a->mealPlan?->name, 'since' => $a->starts_on, 'room_fee' => (float) $a->room_fee, 'meal_fee' => (float) $a->meal_fee, 'total' => round((float) $a->room_fee + (float) $a->meal_fee, 2)])->all();

        return $this->result([R::col('hostel', __('Hostel')), R::col('room', __('Room')), R::col('bed', __('Bed')), R::col('code', __('Student ID')), R::col('student', __('Student')), R::col('class', __('Class')),
            R::col('section', __('Section')), R::col('phone', __('Phone')), R::col('meal', __('Meal plan')), R::col('since', __('Since'), 'date'), R::col('room_fee', __('Room fee'), 'money', true),
            R::col('meal_fee', __('Meal fee'), 'money', true), R::col('total', __('Monthly total'), 'money', true)], $rows);
    }
}
