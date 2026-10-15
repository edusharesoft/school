<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\Period;
use App\Models\Academic\Room;
use App\Models\Academic\Subject;
use App\Models\Academic\TimetableEntry;
use App\Models\Branch;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{setup:array, periods:\Illuminate\Support\Collection, math:Subject, english:Subject, room:Room, teacher:\App\Models\User} */
    protected function routineFixture(Branch $branch): array
    {
        $setup = $this->academicSetup($branch);
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher, 'name' => 'Rina Teacher']);

        return $this->inBranch($branch, function () use ($setup, $teacher) {
            $math = Subject::query()->create(['name' => 'Mathematics', 'code' => 'MATH', 'type' => 'theory', 'is_active' => true]);
            $english = Subject::query()->create(['name' => 'English', 'code' => 'ENG', 'type' => 'theory', 'is_active' => true]);
            foreach ([$math, $english] as $i => $s) {
                ClassSubject::query()->create(['school_class_id' => $setup['class']->id, 'subject_id' => $s->id, 'category' => 'compulsory', 'full_marks' => 100, 'pass_marks' => 33, 'sort_order' => $i]);
            }
            $periods = collect([
                Period::query()->create(['name' => '1st', 'shift' => 'day', 'starts_at' => '08:00', 'ends_at' => '08:45', 'sort_order' => 1]),
                Period::query()->create(['name' => 'Tiffin', 'shift' => 'day', 'starts_at' => '08:45', 'ends_at' => '09:15', 'is_break' => true, 'sort_order' => 2]),
                Period::query()->create(['name' => '2nd', 'shift' => 'day', 'starts_at' => '09:15', 'ends_at' => '10:00', 'sort_order' => 3]),
            ]);
            $room = Room::query()->create(['name' => 'Room 101', 'code' => 'R101', 'type' => 'classroom', 'capacity' => 40, 'is_active' => true]);

            return compact('setup', 'periods', 'math', 'english', 'room', 'teacher');
        });
    }

    protected function payload(array $f, $section, array $cells): array
    {
        return ['academic_session_id' => $f['setup']['session']->id, 'section_id' => $section->id, 'cells' => $cells];
    }

    public function test_periods_can_be_managed_and_validated(): void
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');
        $url = $this->branchUrl($branch, 'academics/periods');

        $this->actingOnBranch($admin, $branch)->post($url, ['name' => '1st period', 'shift' => 'day', 'starts_at' => '08:00', 'ends_at' => '08:45'])->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->post($url, ['name' => 'Bad', 'shift' => 'day', 'starts_at' => '09:00', 'ends_at' => '08:00'])->assertSessionHasErrors('ends_at');
        $this->actingOnBranch($admin, $branch)->get($url)->assertOk()->assertSee('1st period')->assertSee('08:00–08:45');

        $this->assertSame(1, $this->inBranch($branch, fn () => Period::query()->count()));
    }

    public function test_routine_is_saved_and_breaks_and_empty_cells_are_skipped(): void
    {
        $branch = $this->makeBranch();
        $f = $this->routineFixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        [$p1, $break, $p2] = $f['periods']->all();

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'academics/timetable'), $this->payload($f, $f['setup']['sectionA'], [
            6 => [
                $p1->id => ['subject_id' => $f['math']->id, 'teacher_id' => $f['teacher']->id, 'room_id' => $f['room']->id],
                $break->id => ['subject_id' => $f['english']->id],
                $p2->id => ['subject_id' => '', 'teacher_id' => '', 'room_id' => ''],
            ],
            7 => [$p2->id => ['subject_id' => $f['english']->id]],
        ]))->assertSessionHasNoErrors();

        $entries = $this->inBranch($branch, fn () => TimetableEntry::query()->orderBy('day_of_week')->get());
        $this->assertCount(2, $entries);
        $this->assertSame($f['teacher']->id, $entries->firstWhere('day_of_week', 6)->teacher_id);

        // Re-saving without a cell clears it.
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'academics/timetable'), $this->payload($f, $f['setup']['sectionA'], [
            6 => [$p1->id => ['subject_id' => $f['math']->id, 'teacher_id' => $f['teacher']->id, 'room_id' => $f['room']->id]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->inBranch($branch, fn () => TimetableEntry::query()->count()));

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'academics/timetable?section_id='.$f['setup']['sectionA']->id))
            ->assertOk()->assertSee('Mathematics')->assertSee('Rina Teacher');
    }

    public function test_teacher_and_room_double_booking_is_rejected_and_nothing_is_saved(): void
    {
        $branch = $this->makeBranch();
        $f = $this->routineFixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $p1 = $f['periods'][0];
        $url = $this->branchUrl($branch, 'academics/timetable');

        $this->actingOnBranch($admin, $branch)->post($url, $this->payload($f, $f['setup']['sectionA'], [
            6 => [$p1->id => ['subject_id' => $f['math']->id, 'teacher_id' => $f['teacher']->id, 'room_id' => $f['room']->id]],
        ]))->assertSessionHasNoErrors();

        // Same teacher, same slot, other section.
        $this->actingOnBranch($admin, $branch)->post($url, $this->payload($f, $f['setup']['sectionB'], [
            6 => [$p1->id => ['subject_id' => $f['english']->id, 'teacher_id' => $f['teacher']->id]],
            7 => [$p1->id => ['subject_id' => $f['english']->id]],
        ]))->assertSessionHasErrors("cells.6.{$p1->id}.teacher_id");

        // Same room, same slot, other section.
        $this->actingOnBranch($admin, $branch)->post($url, $this->payload($f, $f['setup']['sectionB'], [
            6 => [$p1->id => ['subject_id' => $f['english']->id, 'room_id' => $f['room']->id]],
        ]))->assertSessionHasErrors("cells.6.{$p1->id}.room_id");

        $this->assertSame(1, $this->inBranch($branch, fn () => TimetableEntry::query()->count()));

        // A different day is fine.
        $this->actingOnBranch($admin, $branch)->post($url, $this->payload($f, $f['setup']['sectionB'], [
            7 => [$p1->id => ['subject_id' => $f['english']->id, 'teacher_id' => $f['teacher']->id, 'room_id' => $f['room']->id]],
        ]))->assertSessionHasNoErrors();
    }

    public function test_teachers_and_rooms_of_another_branch_are_rejected(): void
    {
        $institution = Institution::factory()->create();
        $branch = $this->makeBranch($institution);
        $other = $this->makeBranch($institution);
        $f = $this->routineFixture($branch);
        $foreignTeacher = $this->branchUser($other, 'teacher', ['user_type' => UserType::Teacher]);
        $foreignRoom = $this->inBranch($other, fn () => Room::query()->create(['name' => 'Other', 'code' => 'O1', 'type' => 'classroom', 'capacity' => 10, 'is_active' => true]));
        $admin = $this->branchUser($branch, 'branch-admin');
        $p1 = $f['periods'][0];

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'academics/timetable'), $this->payload($f, $f['setup']['sectionA'], [
            6 => [$p1->id => ['subject_id' => $f['math']->id, 'teacher_id' => $foreignTeacher->id, 'room_id' => $foreignRoom->id]],
        ]))->assertSessionHasErrors(["cells.6.{$p1->id}.teacher_id", "cells.6.{$p1->id}.room_id"]);

        $this->assertSame(0, $this->inBranch($branch, fn () => TimetableEntry::query()->count()));
    }

    public function test_teacher_sees_own_routine_in_the_portal_and_cannot_edit_routines(): void
    {
        $branch = $this->makeBranch();
        $f = $this->routineFixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $p1 = $f['periods'][0];

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'academics/timetable'), $this->payload($f, $f['setup']['sectionA'], [
            6 => [$p1->id => ['subject_id' => $f['math']->id, 'teacher_id' => $f['teacher']->id, 'room_id' => $f['room']->id]],
        ]))->assertSessionHasNoErrors();

        $this->actingOnBranch($f['teacher'], $branch)->get($this->branchUrl($branch, 'teacher/timetable'))
            ->assertOk()->assertSee('Mathematics')->assertSee('Room 101')->assertSee('1 class per week');

        $this->actingOnBranch($f['teacher'], $branch)->post($this->branchUrl($branch, 'academics/timetable'), $this->payload($f, $f['setup']['sectionA'], []))
            ->assertForbidden();

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'academics/timetable/rooms?room_id='.$f['room']->id))
            ->assertOk()->assertSee('Rina Teacher');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'academics/timetable/teachers?teacher_id='.$f['teacher']->id))
            ->assertOk()->assertSee('Mathematics');
    }
}
