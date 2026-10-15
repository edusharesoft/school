<?php

namespace App\Services\Hostel;

use App\Models\Hostel\Allocation;
use App\Models\Hostel\Attendance;
use App\Models\Hostel\Bed;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\MealPlan;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Operations\ServiceFees;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Bed allocation (one student per bed, gender-matched hostels), transfers, vacating, meal plans, roll call and monthly fees. */
class HostelService
{
    public function __construct(protected ServiceFees $fees) {}

    /** @return array{beds:int, occupied:int, free:int} */
    public function occupancy(Hostel $hostel): array
    {
        $beds = Bed::query()->where('is_active', true)->whereHas('room', fn ($q) => $q->where('hostel_id', $hostel->id)->where('is_active', true))->count();
        $occupied = Allocation::query()->where('hostel_id', $hostel->id)->where('status', 'active')->count();

        return ['beds' => $beds, 'occupied' => $occupied, 'free' => max(0, $beds - $occupied)];
    }

    public function allocate(Student $student, Bed $bed, ?MealPlan $plan = null, ?string $startsOn = null, ?User $by = null): Allocation
    {
        if (! $student->isActive()) {
            throw ValidationException::withMessages(['student_id' => __('Only active students can live in the hostel.')]);
        }

        return DB::transaction(function () use ($student, $bed, $plan, $startsOn, $by) {
            $bed = Bed::query()->lockForUpdate()->with('room.hostel')->findOrFail($bed->id);
            $room = $bed->room;
            $hostel = $room?->hostel;
            if (! $bed->is_active || ! $room || ! $room->is_active || ! $hostel || ! $hostel->is_active) {
                throw ValidationException::withMessages(['hostel_bed_id' => __('This bed is not available.')]);
            }
            if (($hostel->type === 'boys' && $student->gender !== 'male') || ($hostel->type === 'girls' && $student->gender !== 'female')) {
                throw ValidationException::withMessages(['student_id' => __('This is a :type hostel — the student\'s gender does not match.', ['type' => __($hostel->type)])]);
            }
            if (Allocation::query()->where('student_id', $student->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['student_id' => __('This student already has a bed — transfer or vacate it first.')]);
            }
            if (Allocation::query()->where('hostel_bed_id', $bed->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['hostel_bed_id' => __('This bed is already taken.')]);
            }
            if ($plan && ! $plan->is_active) {
                throw ValidationException::withMessages(['hostel_meal_plan_id' => __('This meal plan is not offered any more.')]);
            }

            return Allocation::query()->create([
                'student_id' => $student->id, 'hostel_id' => $hostel->id, 'hostel_room_id' => $room->id, 'hostel_bed_id' => $bed->id, 'hostel_meal_plan_id' => $plan?->id,
                'room_fee' => (float) $room->monthly_fee, 'meal_fee' => (float) ($plan?->monthly_fee ?? 0), 'starts_on' => $startsOn ?: Carbon::today(), 'status' => 'active', 'created_by' => $by?->id,
            ]);
        });
    }

    public function transfer(Allocation $allocation, Bed $bed, ?User $by, ?string $reason = null): Allocation
    {
        return DB::transaction(function () use ($allocation, $bed, $by, $reason) {
            $allocation = Allocation::query()->lockForUpdate()->with('mealPlan')->findOrFail($allocation->id);
            if ($allocation->status !== 'active') {
                throw ValidationException::withMessages(['allocation' => __('This allocation has already ended.')]);
            }
            if ((int) $allocation->hostel_bed_id === (int) $bed->id) {
                throw ValidationException::withMessages(['hostel_bed_id' => __('The student is already in this bed.')]);
            }
            $student = Student::query()->findOrFail($allocation->student_id);
            $plan = $allocation->mealPlan?->is_active ? $allocation->mealPlan : null;
            $allocation->forceFill(['status' => 'transferred', 'ends_on' => Carbon::today(), 'end_reason' => $reason])->save();

            return $this->allocate($student, $bed, $plan, Carbon::today()->toDateString(), $by);
        });
    }

    public function vacate(Allocation $allocation, ?string $endsOn = null, ?string $reason = null): Allocation
    {
        if ($allocation->status !== 'active') {
            throw ValidationException::withMessages(['allocation' => __('This allocation has already ended.')]);
        }
        $end = $endsOn ? Carbon::parse($endsOn)->startOfDay() : Carbon::today();
        if ($end->lt($allocation->starts_on)) {
            throw ValidationException::withMessages(['ends_on' => __('The end date cannot be before the start date.')]);
        }
        $allocation->forceFill(['status' => 'vacated', 'ends_on' => $end, 'end_reason' => $reason])->save();

        return $allocation;
    }

    public function setMealPlan(Allocation $allocation, ?MealPlan $plan): Allocation
    {
        if ($allocation->status !== 'active') {
            throw ValidationException::withMessages(['allocation' => __('This allocation has already ended.')]);
        }
        $allocation->forceFill(['hostel_meal_plan_id' => $plan?->id, 'meal_fee' => (float) ($plan?->monthly_fee ?? 0)])->save();

        return $allocation;
    }

    /**
     * Roll call: only students who live in the hostel are accepted.
     *
     * @param  array<int,string>  $statuses student id => present|absent|leave|late
     */
    public function markAttendance(Hostel $hostel, string $date, array $statuses, array $notes, ?User $by): int
    {
        if (Carbon::parse($date)->isFuture()) {
            throw ValidationException::withMessages(['date' => __('The date cannot be in the future.')]);
        }
        $residents = Allocation::query()->where('hostel_id', $hostel->id)->where('status', 'active')->pluck('student_id')->flip();
        $count = 0;
        foreach ($statuses as $studentId => $status) {
            if (! $residents->has((int) $studentId) || ! in_array($status, Attendance::STATUSES, true)) {
                continue;
            }
            Attendance::query()->updateOrCreate(['student_id' => (int) $studentId, 'date' => $date], ['hostel_id' => $hostel->id, 'status' => $status, 'note' => $notes[$studentId] ?? null, 'marked_by' => $by?->id]);
            $count++;
        }

        return $count;
    }

    /** @return array{created:int, skipped:int, period:string} */
    public function generateFees(string $period, ?User $by = null): array
    {
        $start = ServiceFees::period($period);
        $end = $start->endOfMonth();
        $head = $this->fees->head('hostel', 'Hostel fee', 'হোস্টেল ফি');
        $stats = ['created' => 0, 'skipped' => 0, 'period' => $period];

        Allocation::query()->with(['student', 'hostel:id,name', 'room:id,room_no', 'mealPlan:id,name'])
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start->toDateString()))->orderBy('id')->get()
            ->each(function (Allocation $a) use ($period, $head, $by, $start, &$stats) {
                if (! $a->student || $a->student->status !== 'active' || $a->monthlyTotal() <= 0) {
                    return;
                }
                $items = [];
                if ((float) $a->room_fee > 0) {
                    $items[] = ['fee_head_id' => $head->id, 'amount' => (float) $a->room_fee, 'description' => __('Hostel — :hostel, room :room', ['hostel' => $a->hostel?->name, 'room' => $a->room?->room_no])];
                }
                if ((float) $a->meal_fee > 0) {
                    $items[] = ['fee_head_id' => $head->id, 'amount' => (float) $a->meal_fee, 'description' => __('Meals — :plan', ['plan' => $a->mealPlan?->name ?? __('Meal plan')])];
                }
                $invoice = $this->fees->bill($a->student, $items, __('Hostel fee for :month', ['month' => $start->format('F Y')]), $period, "hostel:{$a->id}:{$period}", $by);
                $stats[$invoice ? 'created' : 'skipped']++;
            });

        return $stats;
    }
}
