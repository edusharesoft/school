<?php

namespace App\Services\Transport;

use App\Models\Students\Student;
use App\Models\Transport\Allocation;
use App\Models\Transport\Driver;
use App\Models\Transport\DriverAttendance;
use App\Models\Transport\Stop;
use App\Models\Transport\TransportRoute;
use App\Models\User;
use App\Services\Operations\ServiceFees;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Student seats on routes (capacity of the route's vehicle is respected), monthly fees and driver attendance. */
class TransportService
{
    public function __construct(protected ServiceFees $fees) {}

    public function activeSeats(TransportRoute $route): int
    {
        return Allocation::query()->where('transport_route_id', $route->id)->where('status', 'active')->count();
    }

    /** @return int|null seats left, null when the route has no vehicle (no limit known) */
    public function seatsLeft(TransportRoute $route): ?int
    {
        $route->loadMissing('vehicle');

        return $route->vehicle ? max(0, $route->vehicle->capacity - $this->activeSeats($route)) : null;
    }

    public function allocate(Student $student, Stop $stop, ?float $fee = null, ?string $startsOn = null, ?User $by = null, ?string $note = null): Allocation
    {
        if (! $student->isActive()) {
            throw ValidationException::withMessages(['student_id' => __('Only active students can use school transport.')]);
        }

        return DB::transaction(function () use ($student, $stop, $fee, $startsOn, $by, $note) {
            $route = TransportRoute::query()->lockForUpdate()->with('vehicle')->findOrFail($stop->transport_route_id);
            if (! $route->is_active) {
                throw ValidationException::withMessages(['transport_stop_id' => __('This route is not in service.')]);
            }
            if ($route->vehicle && $route->vehicle->status === 'retired') {
                throw ValidationException::withMessages(['transport_stop_id' => __('The vehicle of this route is retired.')]);
            }
            if (Allocation::query()->where('student_id', $student->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['student_id' => __('This student already has a transport seat — end or transfer it first.')]);
            }
            if ($route->vehicle && $this->activeSeats($route) >= $route->vehicle->capacity) {
                throw ValidationException::withMessages(['transport_stop_id' => __('The bus is full: :n seats are taken.', ['n' => $route->vehicle->capacity])]);
            }

            return Allocation::query()->create([
                'student_id' => $student->id, 'transport_route_id' => $route->id, 'transport_stop_id' => $stop->id,
                'monthly_fee' => round($fee ?? $stop->fee(), 2), 'starts_on' => $startsOn ?: Carbon::today(), 'status' => 'active', 'note' => $note, 'created_by' => $by?->id,
            ]);
        });
    }

    /** Move a student to another stop / route: the old seat ends yesterday-or-today and a new one starts, so billing history stays correct. */
    public function transfer(Allocation $allocation, Stop $stop, ?float $fee, ?User $by): Allocation
    {
        return DB::transaction(function () use ($allocation, $stop, $fee, $by) {
            $allocation = Allocation::query()->lockForUpdate()->findOrFail($allocation->id);
            if ($allocation->status !== 'active') {
                throw ValidationException::withMessages(['allocation' => __('This seat has already ended.')]);
            }
            if ((int) $allocation->transport_stop_id === (int) $stop->id) {
                throw ValidationException::withMessages(['transport_stop_id' => __('The student already uses this stop.')]);
            }
            $student = Student::query()->findOrFail($allocation->student_id);
            $allocation->forceFill(['status' => 'ended', 'ends_on' => Carbon::today()])->save();

            return $this->allocate($student, $stop, $fee, Carbon::today()->toDateString(), $by, __('Transferred from stop :s', ['s' => $allocation->stop?->name]));
        });
    }

    public function end(Allocation $allocation, ?string $endsOn = null): Allocation
    {
        if ($allocation->status !== 'active') {
            throw ValidationException::withMessages(['allocation' => __('This seat has already ended.')]);
        }
        $end = $endsOn ? Carbon::parse($endsOn)->startOfDay() : Carbon::today();
        if ($end->lt($allocation->starts_on)) {
            throw ValidationException::withMessages(['ends_on' => __('The end date cannot be before the start date.')]);
        }
        $allocation->forceFill(['status' => 'ended', 'ends_on' => $end])->save();

        return $allocation;
    }

    /**
     * One transport invoice per seat for the month (idempotent). A seat is billed for every month it overlaps,
     * in full — pro-rating is not applied.
     *
     * @return array{created:int, skipped:int, period:string}
     */
    public function generateFees(string $period, ?User $by = null): array
    {
        $start = ServiceFees::period($period);
        $end = $start->endOfMonth();
        $head = $this->fees->head('transport', 'Transport fee', 'পরিবহন ফি');
        $stats = ['created' => 0, 'skipped' => 0, 'period' => $period];

        Allocation::query()->with(['student', 'route:id,name,code', 'stop:id,name'])
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start->toDateString()))
            ->where('monthly_fee', '>', 0)->orderBy('id')->get()->each(function (Allocation $a) use ($period, $head, $by, $start, &$stats) {
                if (! $a->student || $a->student->status !== 'active') {
                    return;
                }
                $invoice = $this->fees->bill($a->student, [[
                    'fee_head_id' => $head->id, 'amount' => (float) $a->monthly_fee,
                    'description' => __('Transport — :route (:stop)', ['route' => $a->route?->name, 'stop' => $a->stop?->name]),
                ]], __('Transport fee for :month', ['month' => $start->format('F Y')]), $period, "transport:{$a->id}:{$period}", $by);
                $stats[$invoice ? 'created' : 'skipped']++;
            });

        return $stats;
    }

    /** @param  array<int,string>  $statuses driver id => present|absent|leave */
    public function markDrivers(string $date, array $statuses, array $notes = []): int
    {
        $count = 0;
        $valid = Driver::query()->where('is_active', true)->pluck('id')->flip();
        foreach ($statuses as $driverId => $status) {
            if (! $valid->has((int) $driverId) || ! in_array($status, ['present', 'absent', 'leave'], true)) {
                continue;
            }
            DriverAttendance::query()->updateOrCreate(['transport_driver_id' => (int) $driverId, 'date' => $date], ['status' => $status, 'note' => $notes[$driverId] ?? null]);
            $count++;
        }

        return $count;
    }
}
