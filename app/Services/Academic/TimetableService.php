<?php

namespace App\Services\Academic;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Period;
use App\Models\Academic\Room;
use App\Models\Academic\Section;
use App\Models\Academic\Subject;
use App\Models\Academic\TimetableEntry;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Class routine builder with conflict detection (master prompt §19):
 * a section slot holds one entry (unique key); a teacher and a room can be in only one section per
 * day & period in a session. Saving a section's grid is all-or-nothing and reports every conflict.
 */
class TimetableService
{
    public function __construct(protected TenantContext $tenant) {}

    /** @return Collection<string,TimetableEntry> keyed "day-period" */
    public function grid(Section $section, AcademicSession $session): Collection
    {
        return TimetableEntry::query()->with(['subject:id,name,code', 'teacher:id,name', 'room:id,name,code'])
            ->where('academic_session_id', $session->id)->where('section_id', $section->id)
            ->get()->keyBy(fn ($e) => $e->day_of_week.'-'.$e->period_id);
    }

    /**
     * @param  array<int|string,array<int|string,array{subject_id?:mixed,teacher_id?:mixed,room_id?:mixed,note?:mixed}>>  $cells  [day][period_id]
     * @return array{saved:int, removed:int}
     */
    public function save(Section $section, AcademicSession $session, array $cells): array
    {
        $periods = Period::query()->pluck('is_break', 'id');
        $teachers = $this->teacherIds();
        $subjects = Subject::query()->pluck('id')->flip();
        $rooms = Room::query()->pluck('id')->flip();

        $rows = [];
        $errors = [];
        foreach ($cells as $day => $byPeriod) {
            $day = (int) $day;
            if ($day < 1 || $day > 7) {
                continue;
            }
            foreach ((array) $byPeriod as $periodId => $cell) {
                $periodId = (int) $periodId;
                $subjectId = ($cell['subject_id'] ?? null) ? (int) $cell['subject_id'] : null;
                $teacherId = ($cell['teacher_id'] ?? null) ? (int) $cell['teacher_id'] : null;
                $roomId = ($cell['room_id'] ?? null) ? (int) $cell['room_id'] : null;
                $note = isset($cell['note']) && trim((string) $cell['note']) !== '' ? mb_substr(trim((string) $cell['note']), 0, 100) : null;

                if (! $periods->has($periodId) || $periods[$periodId]) {
                    continue; // unknown period or a break
                }
                if (! $subjectId && ! $teacherId && ! $roomId && ! $note) {
                    continue; // empty cell = free period
                }

                $slot = TimetableEntry::dayName($day).' / '.$periodId;
                if ($subjectId && ! $subjects->has($subjectId)) {
                    $errors["cells.$day.$periodId.subject_id"] = __('Unknown subject in :slot.', ['slot' => $slot]);
                }
                if ($teacherId && ! $teachers->has($teacherId)) {
                    $errors["cells.$day.$periodId.teacher_id"] = __('The selected teacher cannot teach in this branch (:slot).', ['slot' => $slot]);
                }
                if ($roomId && ! $rooms->has($roomId)) {
                    $errors["cells.$day.$periodId.room_id"] = __('Unknown room in :slot.', ['slot' => $slot]);
                }

                $rows["$day-$periodId"] = compact('day', 'periodId', 'subjectId', 'teacherId', 'roomId', 'note');
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($section, $session, $rows) {
            $this->assertNoConflicts($section, $session, $rows);

            $existing = TimetableEntry::query()->where('academic_session_id', $session->id)->where('section_id', $section->id)
                ->lockForUpdate()->get()->keyBy(fn ($e) => $e->day_of_week.'-'.$e->period_id);

            $saved = 0;
            foreach ($rows as $key => $r) {
                $attributes = ['subject_id' => $r['subjectId'], 'teacher_id' => $r['teacherId'], 'room_id' => $r['roomId'], 'note' => $r['note']];
                $entry = $existing->get($key);
                if ($entry) {
                    $entry->fill($attributes);
                    if ($entry->isDirty()) {
                        $entry->save();
                        $saved++;
                    }
                } else {
                    TimetableEntry::query()->create($attributes + [
                        'academic_session_id' => $session->id, 'section_id' => $section->id, 'day_of_week' => $r['day'], 'period_id' => $r['periodId'],
                    ]);
                    $saved++;
                }
            }

            $removed = 0;
            foreach ($existing as $key => $entry) {
                if (! isset($rows[$key])) {
                    $entry->delete();
                    $removed++;
                }
            }

            return ['saved' => $saved, 'removed' => $removed];
        });
    }

    /** Teacher and room double-booking against every other section of the session. */
    protected function assertNoConflicts(Section $section, AcademicSession $session, array $rows): void
    {
        $others = TimetableEntry::query()->with(['section.schoolClass:id,name', 'teacher:id,name', 'room:id,name'])
            ->where('academic_session_id', $session->id)->where('section_id', '!=', $section->id)
            ->where(fn ($q) => $q->whereIn('teacher_id', array_filter(array_column($rows, 'teacherId')))
                ->orWhereIn('room_id', array_filter(array_column($rows, 'roomId'))))
            ->get();

        $periodNames = Period::query()->pluck('name', 'id');
        $errors = [];
        foreach ($rows as $r) {
            foreach ($others as $o) {
                if ($o->day_of_week !== $r['day'] || $o->period_id !== $r['periodId']) {
                    continue;
                }
                $slot = TimetableEntry::dayName($r['day']).', '.($periodNames[$r['periodId']] ?? '');
                $where = $o->section?->label();
                if ($r['teacherId'] && $o->teacher_id === $r['teacherId']) {
                    $errors["cells.{$r['day']}.{$r['periodId']}.teacher_id"] = __(':teacher already teaches :where on :slot.', ['teacher' => $o->teacher?->name, 'where' => $where, 'slot' => $slot]);
                }
                if ($r['roomId'] && $o->room_id === $r['roomId']) {
                    $errors["cells.{$r['day']}.{$r['periodId']}.room_id"] = __('Room :room is used by :where on :slot.', ['room' => $o->room?->name, 'where' => $where, 'slot' => $slot]);
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Users of type teacher (or with the teacher / class-teacher role) who may work in this branch. @return Collection<int,string> id => name */
    public function teachers(): Collection
    {
        $branch = $this->tenant->branch();

        return User::query()->withAccessToBranch($branch)
            ->where(fn ($q) => $q->where('user_type', 'teacher')->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['teacher', 'class-teacher'])))
            ->where('status', 'active')->orderBy('name')->pluck('name', 'id');
    }

    protected function teacherIds(): Collection
    {
        return $this->teachers()->keys()->flip();
    }

    /** Weekly entries of one teacher (teacher portal / teacher timetable). */
    public function forTeacher(int $userId, AcademicSession $session): Collection
    {
        return TimetableEntry::query()->with(['section.schoolClass:id,name', 'subject:id,name', 'room:id,name'])
            ->where('academic_session_id', $session->id)->where('teacher_id', $userId)
            ->get()->keyBy(fn ($e) => $e->day_of_week.'-'.$e->period_id);
    }

    public function forRoom(int $roomId, AcademicSession $session): Collection
    {
        return TimetableEntry::query()->with(['section.schoolClass:id,name', 'subject:id,name', 'teacher:id,name'])
            ->where('academic_session_id', $session->id)->where('room_id', $roomId)
            ->get()->keyBy(fn ($e) => $e->day_of_week.'-'.$e->period_id);
    }

    /** Working days of the week in school order, without the branch's weekly holidays. @return int[] */
    public function days(): array
    {
        $holidays = array_map('intval', (array) app(\App\Services\Attendance\AttendanceSettings::class)->get('weekly_holidays'));

        return array_values(array_filter(TimetableEntry::WEEK, fn ($d) => ! in_array($d, $holidays, true)));
    }
}
