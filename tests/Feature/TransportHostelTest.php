<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Finance\FeeInvoice;
use App\Models\Hostel\Allocation as HostelAllocation;
use App\Models\Hostel\Attendance as HostelAttendance;
use App\Models\Hostel\Bed;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\MealPlan;
use App\Models\Hostel\Room;
use App\Models\Hostel\Visitor;
use App\Models\Students\Student;
use App\Models\Transport\Allocation;
use App\Models\Transport\Driver;
use App\Models\Transport\DriverAttendance;
use App\Models\Transport\Stop;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\Vehicle;
use App\Services\Hostel\HostelService;
use App\Services\Transport\TransportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Transport seats and hostel beds: capacity, one place per student, fees billed once, gender rules, isolation. */
class TransportHostelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
    }

    protected function world(): array
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $manager = $this->branchUser($branch, 'branch-admin');
        $students = collect(['Anika', 'Chandni', 'Dipu', 'Eshan'])->map(fn ($n) => $this->enrolStudent($branch, $setup, $n));

        return compact('branch', 'setup', 'manager', 'students');
    }

    protected function girl(array $w, string $name = 'Farzana'): Student
    {
        return $this->inBranch($w['branch'], fn () => app(\App\Services\Students\StudentService::class)->admit(
            ['name' => $name, 'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-05'],
            [['name' => 'Guardian of '.$name, 'phone' => '+88017'.random_int(10000000, 99999999), 'relation' => 'mother']],
            ['academic_session_id' => $w['setup']['session']->id, 'school_class_id' => $w['setup']['sectionA']->school_class_id, 'section_id' => $w['setup']['sectionA']->id],
        ));
    }

    protected function url(Branch $branch, string $path): string
    {
        return $this->branchUrl($branch, $path);
    }

    /** @return array{route:TransportRoute, stop:Stop, stop2:Stop, vehicle:Vehicle} */
    protected function route(Branch $branch, int $capacity = 2, float $fee = 1000): array
    {
        return $this->inBranch($branch, function () use ($capacity, $fee) {
            $vehicle = Vehicle::query()->create(['registration_no' => 'DHA-'.random_int(1000, 9999), 'type' => 'bus', 'capacity' => $capacity, 'fuel_type' => 'diesel', 'status' => 'active']);
            $route = TransportRoute::query()->create(['code' => 'R'.random_int(10, 99), 'name' => 'Mirpur line', 'vehicle_id' => $vehicle->id, 'monthly_fee' => $fee, 'is_active' => true]);
            $stop = Stop::query()->create(['transport_route_id' => $route->id, 'name' => 'Mirpur 10', 'sequence' => 1]);
            $stop2 = Stop::query()->create(['transport_route_id' => $route->id, 'name' => 'Pallabi', 'sequence' => 2, 'monthly_fee' => 1500]);

            return compact('route', 'stop', 'stop2', 'vehicle');
        });
    }

    protected function transport(Branch $branch, \Closure $fn): mixed
    {
        return $this->inBranch($branch, fn () => $fn(app(TransportService::class)));
    }

    protected function hostel(Branch $branch, string $type = 'mixed', int $beds = 2, float $roomFee = 3000): array
    {
        return $this->inBranch($branch, function () use ($type, $beds, $roomFee) {
            $hostel = Hostel::query()->create(['name' => 'Hostel '.random_int(100, 999), 'type' => $type, 'is_active' => true]);
            $room = Room::query()->create(['hostel_id' => $hostel->id, 'room_no' => '101', 'type' => 'shared', 'monthly_fee' => $roomFee, 'is_active' => true]);
            $bedModels = collect(range(1, $beds))->map(fn ($i) => $room->beds()->create(['label' => 'B'.$i]));

            return ['hostel' => $hostel, 'room' => $room, 'beds' => $bedModels];
        });
    }

    protected function hs(Branch $branch, \Closure $fn): mixed
    {
        return $this->inBranch($branch, fn () => $fn(app(HostelService::class)));
    }

    protected function fails(\Closure $fn, string $field): void
    {
        try {
            $fn();
            $this->fail('Expected a validation error on '.$field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    // ----- transport -----

    public function test_seat_capacity_and_one_seat_per_student(): void
    {
        $w = $this->world();
        $t = $this->route($w['branch'], 2);
        [$a, $b, $c] = [$w['students'][0], $w['students'][1], $w['students'][2]];

        $seat = $this->transport($w['branch'], fn ($s) => $s->allocate($a, $t['stop']));
        $this->assertSame('1000.00', (string) $seat->monthly_fee);                       // falls back to the route fee
        $this->assertSame('1500.00', (string) $this->transport($w['branch'], fn ($s) => $s->allocate($b, $t['stop2']))->monthly_fee);   // stop fee wins

        $this->fails(fn () => $this->transport($w['branch'], fn ($s) => $s->allocate($c, $t['stop'])), 'transport_stop_id');   // bus is full
        $this->fails(fn () => $this->transport($w['branch'], fn ($s) => $s->allocate($a, $t['stop2'])), 'student_id');           // already seated
        $this->assertSame(0, $this->transport($w['branch'], fn ($s) => $s->seatsLeft($t['route'])));
    }

    public function test_transfer_and_end_keep_history_and_free_the_seat(): void
    {
        $w = $this->world();
        $t = $this->route($w['branch'], 1);
        $a = $w['students'][0];

        $seat = $this->transport($w['branch'], fn ($s) => $s->allocate($a, $t['stop']));
        $new = $this->transport($w['branch'], fn ($s) => $s->transfer($seat, $t['stop2'], null, $w['manager']));
        $this->assertSame('ended', $seat->fresh()->status);
        $this->assertSame('active', $new->status);
        $this->assertSame('1500.00', (string) $new->monthly_fee);
        $this->fails(fn () => $this->transport($w['branch'], fn ($s) => $s->transfer($new, $t['stop2'], null, $w['manager'])), 'transport_stop_id');

        $this->transport($w['branch'], fn ($s) => $s->end($new));
        $this->assertSame(1, $this->transport($w['branch'], fn ($s) => $s->seatsLeft($t['route'])));
        $this->fails(fn () => $this->transport($w['branch'], fn ($s) => $s->end($new->fresh())), 'allocation');
    }

    public function test_inactive_route_and_retired_vehicle_refuse_new_seats(): void
    {
        $w = $this->world();
        $t = $this->route($w['branch'], 5);
        $this->inBranch($w['branch'], fn () => $t['vehicle']->update(['status' => 'retired']));
        $this->fails(fn () => $this->transport($w['branch'], fn ($s) => $s->allocate($w['students'][0], $t['stop'])), 'transport_stop_id');

        $this->inBranch($w['branch'], fn () => $t['vehicle']->update(['status' => 'active']));
        $this->inBranch($w['branch'], fn () => $t['route']->update(['is_active' => false]));
        $this->fails(fn () => $this->transport($w['branch'], fn ($s) => $s->allocate($w['students'][0], $t['stop'])), 'transport_stop_id');
    }

    public function test_transport_fees_are_billed_once_per_month_as_normal_invoices(): void
    {
        $w = $this->world();
        $t = $this->route($w['branch'], 5);
        $this->transport($w['branch'], fn ($s) => $s->allocate($w['students'][0], $t['stop'], null, '2026-02-10'));
        $this->transport($w['branch'], fn ($s) => $s->allocate($w['students'][1], $t['stop2'], null, '2026-03-01'));
        $this->transport($w['branch'], fn ($s) => $s->allocate($w['students'][2], $t['stop'], null, '2026-04-01'));   // starts after the month

        $first = $this->transport($w['branch'], fn ($s) => $s->generateFees('2026-03', $w['manager']));
        $this->assertSame(['created' => 2, 'skipped' => 0, 'period' => '2026-03'], $first);
        $again = $this->transport($w['branch'], fn ($s) => $s->generateFees('2026-03', $w['manager']));
        $this->assertSame(['created' => 0, 'skipped' => 2, 'period' => '2026-03'], $again);

        $invoices = $this->inBranch($w['branch'], fn () => FeeInvoice::query()->where('period', '2026-03')->get());
        $this->assertCount(2, $invoices);
        $this->assertEqualsCanonicalizing([1000.0, 1500.0], $invoices->map(fn ($i) => (float) $i->total)->all());
    }

    public function test_ended_seat_is_not_billed_after_the_end_month(): void
    {
        $w = $this->world();
        $t = $this->route($w['branch'], 5);
        $seat = $this->transport($w['branch'], fn ($s) => $s->allocate($w['students'][0], $t['stop'], null, '2026-01-10'));
        $this->transport($w['branch'], fn ($s) => $s->end($seat, '2026-02-20'));

        $this->assertSame(1, $this->transport($w['branch'], fn ($s) => $s->generateFees('2026-02'))['created']);
        $this->assertSame(0, $this->transport($w['branch'], fn ($s) => $s->generateFees('2026-03'))['created']);
    }

    public function test_driver_attendance_is_saved_once_per_day(): void
    {
        $w = $this->world();
        [$d1, $d2] = $this->inBranch($w['branch'], fn () => [
            Driver::query()->create(['role' => 'driver', 'name' => 'Karim', 'phone' => '01711111111', 'license_no' => 'L-1', 'is_active' => true]),
            Driver::query()->create(['role' => 'helper', 'name' => 'Rahim', 'phone' => '01722222222', 'is_active' => true]),
        ]);
        $this->assertSame(2, $this->transport($w['branch'], fn ($s) => $s->markDrivers('2026-03-15', [$d1->id => 'present', $d2->id => 'leave', 999 => 'present'])));
        $this->transport($w['branch'], fn ($s) => $s->markDrivers('2026-03-15', [$d1->id => 'absent']));
        $rows = $this->inBranch($w['branch'], fn () => DriverAttendance::query()->get());
        $this->assertCount(2, $rows);
        $this->assertSame('absent', $rows->firstWhere('transport_driver_id', $d1->id)->status);
    }

    public function test_transport_screens_work_end_to_end_over_http(): void
    {
        $w = $this->world();
        $admin = $w['manager'];

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/vehicles'), [
            'registration_no' => 'DHA-METRO-11', 'type' => 'bus', 'capacity' => 30, 'fuel_type' => 'diesel', 'status' => 'active',
            'insurance_expiry' => now()->addDays(10)->toDateString(),
        ])->assertSessionHasNoErrors();
        $vehicle = $this->inBranch($w['branch'], fn () => Vehicle::query()->firstOrFail());

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/drivers'), ['role' => 'driver', 'name' => 'Karim', 'phone' => '01711111111', 'license_no' => 'L-77'])->assertSessionHasNoErrors();
        $driver = $this->inBranch($w['branch'], fn () => Driver::query()->firstOrFail());
        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/drivers'), ['role' => 'driver', 'name' => 'No licence', 'phone' => '0171'])->assertSessionHasErrors('license_no');

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/routes'), [
            'code' => 'M-1', 'name' => 'Mirpur', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'monthly_fee' => 1200, 'morning_start' => '06:45', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $route = $this->inBranch($w['branch'], fn () => TransportRoute::query()->firstOrFail());
        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/routes/'.$route->id.'/stops'), ['name' => 'Mirpur 10', 'pickup_time' => '07:00', 'drop_time' => '14:30'])->assertSessionHasNoErrors();
        $stop = $this->inBranch($w['branch'], fn () => Stop::query()->firstOrFail());

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/allocations'), ['student_id' => $w['students'][0]->id, 'transport_stop_id' => $stop->id])->assertSessionHasNoErrors();
        $seat = $this->inBranch($w['branch'], fn () => Allocation::query()->firstOrFail());
        $this->assertSame('1200.00', (string) $seat->monthly_fee);

        foreach (['transport', 'transport/vehicles', 'transport/vehicles/'.$vehicle->id, 'transport/drivers', 'transport/routes', 'transport/routes/'.$route->id, 'transport/routes/'.$route->id.'/sheet', 'transport/allocations', 'transport/fees', 'transport/attendance'] as $p) {
            $this->actingOnBranch($admin, $w['branch'])->get($this->url($w['branch'], $p))->assertOk();
        }
        $this->actingOnBranch($admin, $w['branch'])->get($this->url($w['branch'], 'transport/vehicles/'.$vehicle->id))->assertSee('DHA-METRO-11');

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/vehicles/'.$vehicle->id.'/fuel'), ['date' => '2026-03-10', 'litres' => 40, 'total_cost' => 4400])->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/vehicles/'.$vehicle->id.'/maintenance'), ['date' => '2026-03-11', 'kind' => 'service', 'description' => 'Oil change', 'cost' => 2500])->assertSessionHasNoErrors();

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/fees'), ['period' => '2026-03'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->inBranch($w['branch'], fn () => FeeInvoice::query()->where('period', '2026-03')->count()));

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'transport/allocations/'.$seat->id.'/end'), [])->assertSessionHasNoErrors();
        $this->assertSame('ended', $seat->fresh()->status);
        $this->actingOnBranch($admin, $w['branch'])->delete($this->url($w['branch'], 'transport/vehicles/'.$vehicle->id))->assertRedirect();   // route still uses it → refused or blocked, never a 500
    }

    // ----- hostel -----

    public function test_one_student_per_bed_and_one_bed_per_student(): void
    {
        $w = $this->world();
        $h = $this->hostel($w['branch'], 'mixed', 2);
        [$a, $b] = [$w['students'][0], $w['students'][1]];

        $this->hs($w['branch'], fn ($s) => $s->allocate($a, $h['beds'][0]));
        $this->fails(fn () => $this->hs($w['branch'], fn ($s) => $s->allocate($b, $h['beds'][0])), 'hostel_bed_id');
        $this->fails(fn () => $this->hs($w['branch'], fn ($s) => $s->allocate($a, $h['beds'][1])), 'student_id');
        $this->assertSame(['beds' => 2, 'occupied' => 1, 'free' => 1], $this->hs($w['branch'], fn ($s) => $s->occupancy($h['hostel'])));
    }

    public function test_boys_and_girls_hostels_are_gender_matched(): void
    {
        $w = $this->world();
        $boys = $this->hostel($w['branch'], 'boys', 1);
        $girls = $this->hostel($w['branch'], 'girls', 1);
        $girl = $this->girl($w);

        $this->fails(fn () => $this->hs($w['branch'], fn ($s) => $s->allocate($girl, $boys['beds'][0])), 'student_id');
        $this->fails(fn () => $this->hs($w['branch'], fn ($s) => $s->allocate($w['students'][0], $girls['beds'][0])), 'student_id');
        $this->assertSame('active', $this->hs($w['branch'], fn ($s) => $s->allocate($girl, $girls['beds'][0]))->status);
        $this->assertSame('active', $this->hs($w['branch'], fn ($s) => $s->allocate($w['students'][0], $boys['beds'][0]))->status);
    }

    public function test_transfer_vacate_and_meal_plan(): void
    {
        $w = $this->world();
        $h = $this->hostel($w['branch'], 'mixed', 2);
        $plan = $this->inBranch($w['branch'], fn () => MealPlan::query()->create(['name' => 'Three meals', 'monthly_fee' => 2500, 'is_active' => true]));
        $a = $w['students'][0];

        $stay = $this->hs($w['branch'], fn ($s) => $s->allocate($a, $h['beds'][0], $plan));
        $this->assertSame('5500.00', number_format($stay->monthlyTotal(), 2, '.', ''));
        $moved = $this->hs($w['branch'], fn ($s) => $s->transfer($stay, $h['beds'][1], $w['manager'], 'Closer to window'));
        $this->assertSame('transferred', $stay->fresh()->status);
        $this->assertSame((int) $h['beds'][1]->id, (int) $moved->hostel_bed_id);
        $this->assertSame((int) $plan->id, (int) $moved->hostel_meal_plan_id);   // meal plan follows the student

        $this->hs($w['branch'], fn ($s) => $s->setMealPlan($moved, null));
        $this->assertSame('3000.00', number_format($moved->fresh()->monthlyTotal(), 2, '.', ''));

        $this->hs($w['branch'], fn ($s) => $s->vacate($moved, null, 'Left school'));
        $this->assertSame(['beds' => 2, 'occupied' => 0, 'free' => 2], $this->hs($w['branch'], fn ($s) => $s->occupancy($h['hostel'])));
        $this->fails(fn () => $this->hs($w['branch'], fn ($s) => $s->vacate($moved->fresh())), 'allocation');
    }

    public function test_hostel_fees_bill_room_and_meals_once_per_month(): void
    {
        $w = $this->world();
        $h = $this->hostel($w['branch'], 'mixed', 2, 3000);
        $plan = $this->inBranch($w['branch'], fn () => MealPlan::query()->create(['name' => 'Lunch', 'monthly_fee' => 1500, 'is_active' => true]));
        $this->hs($w['branch'], fn ($s) => $s->allocate($w['students'][0], $h['beds'][0], $plan, '2026-02-01'));
        $this->hs($w['branch'], fn ($s) => $s->allocate($w['students'][1], $h['beds'][1], null, '2026-03-01'));

        $this->assertSame(['created' => 2, 'skipped' => 0, 'period' => '2026-03'], $this->hs($w['branch'], fn ($s) => $s->generateFees('2026-03', $w['manager'])));
        $this->assertSame(['created' => 0, 'skipped' => 2, 'period' => '2026-03'], $this->hs($w['branch'], fn ($s) => $s->generateFees('2026-03', $w['manager'])));

        $invoices = $this->inBranch($w['branch'], fn () => FeeInvoice::query()->where('period', '2026-03')->with('items')->get());
        $this->assertEqualsCanonicalizing([3000.0, 4500.0], $invoices->map(fn ($i) => (float) $i->total)->all());
        $this->assertSame(3, $invoices->sum(fn ($i) => $i->items->count()));   // room + meals, and room alone
    }

    public function test_roll_call_only_accepts_residents(): void
    {
        $w = $this->world();
        $h = $this->hostel($w['branch'], 'mixed', 2);
        $this->hs($w['branch'], fn ($s) => $s->allocate($w['students'][0], $h['beds'][0]));

        $n = $this->hs($w['branch'], fn ($s) => $s->markAttendance($h['hostel'], '2026-03-14', [$w['students'][0]->id => 'present', $w['students'][1]->id => 'present'], [], $w['manager']));
        $this->assertSame(1, $n);
        $this->hs($w['branch'], fn ($s) => $s->markAttendance($h['hostel'], '2026-03-14', [$w['students'][0]->id => 'late'], [], $w['manager']));
        $rows = $this->inBranch($w['branch'], fn () => HostelAttendance::query()->get());
        $this->assertCount(1, $rows);
        $this->assertSame('late', $rows[0]->status);
        $this->fails(fn () => $this->hs($w['branch'], fn ($s) => $s->markAttendance($h['hostel'], '2026-03-16', [], [], $w['manager'])), 'date');
    }

    public function test_hostel_screens_visitors_and_maintenance_over_http(): void
    {
        $w = $this->world();
        $admin = $w['manager'];

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'hostel/buildings'), ['name' => 'Rose Hall', 'type' => 'boys', 'is_active' => 1])->assertSessionHasNoErrors();
        $hostel = $this->inBranch($w['branch'], fn () => Hostel::query()->firstOrFail());
        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'hostel/rooms'), ['hostel_id' => $hostel->id, 'room_no' => '201', 'type' => 'shared', 'monthly_fee' => 2000, 'beds' => 3, 'is_active' => 1])->assertSessionHasNoErrors();
        $room = $this->inBranch($w['branch'], fn () => Room::query()->firstOrFail());
        $this->assertSame(3, $this->inBranch($w['branch'], fn () => Bed::query()->count()));
        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'hostel/rooms'), ['hostel_id' => $hostel->id, 'room_no' => '201', 'type' => 'shared', 'monthly_fee' => 2000, 'beds' => 3, 'is_active' => 1])->assertSessionHasErrors('room_no');

        $bed = $this->inBranch($w['branch'], fn () => Bed::query()->firstOrFail());
        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'hostel/allocations'), ['student_id' => $w['students'][0]->id, 'hostel_bed_id' => $bed->id])->assertSessionHasNoErrors();
        $stay = $this->inBranch($w['branch'], fn () => HostelAllocation::query()->firstOrFail());

        foreach (['hostel', 'hostel/rooms', 'hostel/rooms/'.$room->id, 'hostel/allocations', 'hostel/meal-plans', 'hostel/attendance?hostel_id='.$hostel->id, 'hostel/visitors', 'hostel/maintenance', 'hostel/fees', 'hostel/buildings/'.$hostel->id.'/edit', 'hostel/rooms/create'] as $p) {
            $this->actingOnBranch($admin, $w['branch'])->get($this->url($w['branch'], $p))->assertOk();
        }

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'hostel/visitors'), ['allocation_id' => $stay->id, 'visitor_name' => 'Father', 'relation' => 'father'])->assertSessionHasNoErrors();
        $visit = $this->inBranch($w['branch'], fn () => Visitor::query()->firstOrFail());
        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'hostel/visitors/'.$visit->id.'/checkout'))->assertSessionHasNoErrors();
        $this->assertNotNull($visit->fresh()->out_at);

        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'hostel/maintenance'), ['hostel_id' => $hostel->id, 'hostel_room_id' => $room->id, 'title' => 'Broken fan', 'priority' => 'normal'])->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $w['branch'])->post($this->url($w['branch'], 'hostel/fees'), ['period' => '2026-03'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->inBranch($w['branch'], fn () => FeeInvoice::query()->where('period', '2026-03')->count()));
    }

    // ----- permissions, isolation, scheduler -----

    public function test_teachers_cannot_use_transport_or_hostel_back_office(): void
    {
        $w = $this->world();
        $teacher = $this->branchUser($w['branch'], 'teacher');
        foreach (['transport', 'transport/allocations', 'hostel', 'hostel/allocations'] as $p) {
            $this->actingOnBranch($teacher, $w['branch'])->get($this->url($w['branch'], $p))->assertForbidden();
        }
        $this->actingOnBranch($teacher, $w['branch'])->post($this->url($w['branch'], 'transport/allocations'), [])->assertForbidden();
        $this->actingOnBranch($teacher, $w['branch'])->post($this->url($w['branch'], 'hostel/fees'), ['period' => '2026-03'])->assertForbidden();
    }

    public function test_dedicated_managers_have_access(): void
    {
        $w = $this->world();
        $this->actingOnBranch($this->branchUser($w['branch'], 'transport-manager'), $w['branch'])->get($this->url($w['branch'], 'transport'))->assertOk();
        $this->actingOnBranch($this->branchUser($w['branch'], 'hostel-manager'), $w['branch'])->get($this->url($w['branch'], 'hostel'))->assertOk();
    }

    public function test_other_branches_data_is_invisible_and_unusable(): void
    {
        $w = $this->world();
        $other = $this->makeBranch();
        $theirs = $this->route($other, 5);
        $theirHostel = $this->hostel($other, 'mixed', 1);

        $this->actingOnBranch($w['manager'], $w['branch'])->get($this->url($w['branch'], 'transport/routes/'.$theirs['route']->id))->assertNotFound();
        $this->actingOnBranch($w['manager'], $w['branch'])->get($this->url($w['branch'], 'hostel/rooms/'.$theirHostel['room']->id))->assertNotFound();
        $this->actingOnBranch($w['manager'], $w['branch'])->post($this->url($w['branch'], 'transport/allocations'), ['student_id' => $w['students'][0]->id, 'transport_stop_id' => $theirs['stop']->id])->assertSessionHasErrors('transport_stop_id');
        $this->actingOnBranch($w['manager'], $w['branch'])->post($this->url($w['branch'], 'hostel/allocations'), ['student_id' => $w['students'][0]->id, 'hostel_bed_id' => $theirHostel['beds'][0]->id])->assertSessionHasErrors('hostel_bed_id');
        $this->assertSame(0, $this->inBranch($w['branch'], fn () => Allocation::query()->count() + HostelAllocation::query()->count()));
    }

    public function test_student_lookup_finds_active_students_in_the_branch_only(): void
    {
        $w = $this->world();
        $other = $this->makeBranch();
        $this->enrolStudent($other, $this->academicSetup($other), 'Anika Elsewhere');

        $res = $this->actingOnBranch($w['manager'], $w['branch'])->getJson($this->url($w['branch'], 'operations/students?q=Anika'))->assertOk();
        $this->assertStringContainsString('Anika', json_encode($res->json()));
        $this->assertStringNotContainsString('Elsewhere', json_encode($res->json()));
    }

    public function test_monthly_command_bills_both_services_per_branch_and_is_idempotent(): void
    {
        $w = $this->world();
        $t = $this->route($w['branch'], 5);
        $h = $this->hostel($w['branch'], 'mixed', 1);
        $this->transport($w['branch'], fn ($s) => $s->allocate($w['students'][0], $t['stop'], null, '2026-02-01'));
        $this->hs($w['branch'], fn ($s) => $s->allocate($w['students'][1], $h['beds'][0], null, '2026-02-01'));

        $this->assertSame(0, $this->inBranch($w['branch'], fn () => $this->artisan('services:generate-fees', ['--period' => '2026-03', '--force' => true])->run()));
        $this->assertSame(0, $this->inBranch($w['branch'], fn () => $this->artisan('services:generate-fees', ['--period' => '2026-03', '--force' => true])->run()));
        $this->assertSame(2, $this->inBranch($w['branch'], fn () => FeeInvoice::query()->where('period', '2026-03')->count()));
    }

    public function test_student_and_parent_portal_and_api_show_seat_bed_and_contacts(): void
    {
        $w = $this->world();
        $admin = $w['manager'];
        [$a, $c] = [$w['students'][0], $w['students'][1]];
        foreach ([$a, $c] as $st) {
            $this->actingOnBranch($admin, $w['branch'])->post($this->branchUrl($w['branch'], "students/{$st->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        }
        $guardian = $this->inBranch($w['branch'], fn () => $a->guardians()->first());
        $this->actingOnBranch($admin, $w['branch'])->post($this->branchUrl($w['branch'], "students/{$a->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $studentA = \App\Models\User::query()->where('username', strtolower($a->student_code))->sole();
        $studentC = \App\Models\User::query()->where('username', strtolower($c->student_code))->sole();
        $parentA = \App\Models\User::query()->where('phone', $guardian->phone)->sole();

        $t = $this->route($w['branch'], 5);
        $this->inBranch($w['branch'], function () use ($t) {
            $driver = Driver::query()->create(['role' => 'driver', 'name' => 'Karim Driver', 'phone' => '01711000000', 'license_no' => 'L-9', 'is_active' => true]);
            $t['route']->update(['driver_id' => $driver->id, 'morning_start' => '06:40']);
            $t['stop']->update(['pickup_time' => '07:05']);
            $t['vehicle']->update(['tracking_url' => 'https://tracker.example.com/bus/12']);
        });
        $this->transport($w['branch'], fn ($s) => $s->allocate($a, $t['stop']));
        $h = $this->hostel($w['branch'], 'mixed', 2);
        $this->hs($w['branch'], fn ($s) => $s->allocate($a, $h['beds'][0]));
        $this->hs($w['branch'], fn ($s) => $s->markAttendance($h['hostel'], '2026-03-14', [$a->id => 'present'], [], $admin));

        $this->actingOnBranch($studentA, $w['branch'])->get($this->branchUrl($w['branch'], 'student/facilities'))->assertOk()
            ->assertSee('Mirpur line')->assertSee('Karim Driver')->assertSee('https://tracker.example.com/bus/12')->assertSee($h['hostel']->name);
        $this->actingOnBranch($studentC, $w['branch'])->get($this->branchUrl($w['branch'], 'student/facilities'))->assertOk()->assertSee('No transport seat is assigned.')->assertDontSee('Karim Driver');
        $this->actingOnBranch($parentA, $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$a->id}/facilities"))->assertOk()->assertSee('Karim Driver');
        $this->actingOnBranch($parentA, $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$c->id}/facilities"))->assertNotFound();
        $this->actingOnBranch($admin, $w['branch'])->get($this->branchUrl($w['branch'], 'student/facilities'))->assertForbidden();

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $host = fn ($path) => $this->branchUrl($w['branch'], 'api/v1/'.$path);
        $token = $this->postJson($host('auth/login'), ['login' => $studentA->username, 'password' => 'secret123', 'device_name' => 'phpunit'])->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson($host('my/facilities'))->assertOk()
            ->assertJsonPath('data.0.student.name', 'Anika')->assertJsonPath('data.0.transport.route.code', $t['route']->code)->assertJsonPath('data.0.transport.stop.pickup_time', '07:05')
            ->assertJsonPath('data.0.transport.driver.name', 'Karim Driver')->assertJsonPath('data.0.transport.tracking_url', 'https://tracker.example.com/bus/12')
            ->assertJsonPath('data.0.hostel.room.room_no', '101')->assertJsonPath('data.0.hostel.recent_attendance.0.status', 'present');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson($host('my/facilities'))->assertUnauthorized();
    }
}
