<?php

namespace App\Services\Learning;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\Section;
use App\Models\Academic\TimetableEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may publish learning content for which class / section.
 *
 *  - Back-office users (admin, principal, coordinator …) with the module permission: every class and section.
 *  - Teachers (teacher portal): only the sections they are class teacher of or teach in the routine
 *    (timetable) of the current session — and only subjects that belong to the class.
 */
class LearningAccess
{
    public function isTeacher(User $user): bool
    {
        return $user->portal() === 'teacher';
    }

    /** @return Collection<int,int> section ids the user may teach (null → unrestricted, represented by all active sections) */
    public function sectionIds(User $user): Collection
    {
        if (! $this->isTeacher($user)) {
            return Section::query()->where('is_active', true)->pluck('id');
        }

        $sessionId = AcademicSession::query()->current()->value('id');
        $fromRoutine = TimetableEntry::query()->where('teacher_id', $user->id)
            ->when($sessionId, fn ($q, $s) => $q->where('academic_session_id', $s))->pluck('section_id');

        return Section::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('class_teacher_id', $user->id)->orWhereIn('id', $fromRoutine))
            ->pluck('id');
    }

    /** Sections (with class) for the form dropdowns. @return Collection<int,Section> */
    public function sections(User $user): Collection
    {
        return Section::query()->with('schoolClass:id,name,numeric_order')->whereIn('id', $this->sectionIds($user))->get()
            ->sortBy(fn (Section $s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->values();
    }

    /** May the user publish for this class (and section)? Teachers must name one of their sections. */
    public function canTeach(User $user, int $classId, ?int $sectionId): bool
    {
        if (! $this->isTeacher($user)) {
            return true;
        }
        if (! $sectionId) {
            return false;
        }

        return $this->sectionIds($user)->contains($sectionId)
            && Section::query()->whereKey($sectionId)->where('school_class_id', $classId)->exists();
    }

    public function subjectBelongsToClass(int $classId, ?int $subjectId): bool
    {
        return ! $subjectId || ClassSubject::query()->where('school_class_id', $classId)->where('subject_id', $subjectId)->exists();
    }

    /** Restrict a query of learning content to what the user manages. Back-office users see everything. */
    public function scopeFor(User $user, $query)
    {
        if (! $this->isTeacher($user)) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    /** Like scopeFor(), but a teacher also sees classes the back office assigned to them (teacher_id). */
    public function scopeForHost(User $user, $query)
    {
        if (! $this->isTeacher($user)) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('created_by', $user->id)->orWhere('teacher_id', $user->id));
    }

    /** May the user change / see the evaluation of this content item? Owner (or assigned host), or any back-office user. */
    public function manages(User $user, object $item): bool
    {
        return ! $this->isTeacher($user) || (int) $item->created_by === $user->id
            || (isset($item->teacher_id) && (int) $item->teacher_id === $user->id);
    }
}
