<?php

namespace App\Services\Operations;

use App\Models\Hostel\Allocation as HostelAllocation;
use App\Models\Hostel\Attendance as HostelAttendance;
use App\Models\Students\Student;
use App\Models\Transport\Allocation as TransportAllocation;

/**
 * What a student (or a parent) may see about school transport and hostel: the route, stop and timings, the people to call,
 * the bed and the recent roll call. Shared by the portal pages and the mobile API so both show the same facts.
 */
class StudentFacilities
{
    /** @return array{transport: array|null, hostel: array|null} */
    public function for(Student $student): array
    {
        return ['transport' => $this->transport($student), 'hostel' => $this->hostel($student)];
    }

    public function transport(Student $student): ?array
    {
        $a = TransportAllocation::query()->where('student_id', $student->id)->where('status', 'active')->with(['route.vehicle', 'route.driver', 'route.helper', 'stop'])->first();
        if (! $a || ! $a->route) {
            return null;
        }
        $r = $a->route;
        $person = fn ($p) => $p ? ['name' => $p->name, 'phone' => $p->phone] : null;
        $link = $r->vehicle?->tracking_url;

        return [
            'route' => ['code' => $r->code, 'name' => $r->name],
            'stop' => ['name' => $a->stop?->name, 'pickup_time' => $this->time($a->stop?->pickup_time), 'drop_time' => $this->time($a->stop?->drop_time)],
            'morning_start' => $this->time($r->morning_start), 'afternoon_start' => $this->time($r->afternoon_start),
            'vehicle' => $r->vehicle ? ['registration_no' => $r->vehicle->registration_no, 'name' => $r->vehicle->name, 'type' => $r->vehicle->type] : null,
            'driver' => $person($r->driver), 'helper' => $person($r->helper),
            'tracking_url' => $link && str_starts_with($link, 'https://') ? $link : null,
            'monthly_fee' => (float) $a->monthly_fee, 'since' => $a->starts_on?->toDateString(),
        ];
    }

    public function hostel(Student $student): ?array
    {
        $a = HostelAllocation::query()->where('student_id', $student->id)->where('status', 'active')->with(['hostel.warden', 'room', 'bed', 'mealPlan'])->first();
        if (! $a || ! $a->hostel) {
            return null;
        }
        $warden = $a->hostel->warden;
        $recent = HostelAttendance::query()->where('student_id', $student->id)->orderByDesc('date')->limit(7)->get(['date', 'status']);

        return [
            'hostel' => ['name' => $a->hostel->name, 'type' => $a->hostel->type, 'phone' => $a->hostel->phone],
            'room' => ['room_no' => $a->room?->room_no, 'building' => $a->room?->building, 'floor' => $a->room?->floor], 'bed' => $a->bed?->label,
            'warden' => $warden ? ['name' => $warden->name, 'phone' => $warden->phone] : null,
            'meal_plan' => $a->mealPlan?->name, 'monthly_fee' => $a->monthlyTotal(), 'since' => $a->starts_on?->toDateString(),
            'recent_attendance' => $recent->map(fn ($r) => ['date' => $r->date->toDateString(), 'status' => $r->status])->values()->all(),
        ];
    }

    protected function time(mixed $value): ?string
    {
        return $value ? substr((string) $value, 0, 5) : null;
    }
}
