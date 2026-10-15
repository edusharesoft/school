<?php

namespace App\Services\Communication;

use App\Enums\UserType;
use App\Models\Communication\Notice;
use App\Models\HR\Employee;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\User;

/**
 * Who may read a notice. A user belongs to one or more groups in the current branch:
 *   staff (any employee / back-office user), teachers, students (their class, section, id),
 *   guardians (their children's classes, sections and ids).
 */
class NoticeAudience
{
    /** @var array<int,array> per user id */
    protected array $contexts = [];

    public function context(User $user): array
    {
        return $this->contexts[$user->id] ??= $this->build($user);
    }

    public function matches(Notice $notice, User $user): bool
    {
        $ctx = $this->context($user);
        $filter = (array) $notice->audience_filter;
        $hit = fn (string $key, array $values) => array_intersect(array_map('intval', (array) ($filter[$key] ?? [])), $values) !== [];

        return match ($notice->audience) {
            'all' => $ctx['staff'] || $ctx['student'] || $ctx['guardian'],
            'staff' => $ctx['staff'],
            'teachers' => $ctx['teacher'],
            'students' => $ctx['student'],
            'guardians' => $ctx['guardian'],
            'classes' => ($ctx['student'] || $ctx['guardian']) && ($hit('class_ids', $ctx['class_ids']) || $hit('section_ids', $ctx['section_ids'])),
            'students_specific' => ($ctx['student'] || $ctx['guardian']) && $hit('student_ids', $ctx['student_ids']),
            default => false,
        };
    }

    /** May the user open the notice (audience member, or someone who manages notices)? */
    public function canView(Notice $notice, User $user): bool
    {
        if ($user->can('notices.view') && $user->portal() === 'admin') {
            return true;
        }

        return $notice->isLive() && $this->matches($notice, $user);
    }

    protected function build(User $user): array
    {
        $ctx = ['staff' => false, 'teacher' => false, 'student' => false, 'guardian' => false, 'class_ids' => [], 'section_ids' => [], 'student_ids' => []];
        $type = $user->user_type;

        if (in_array($type, [UserType::Staff, UserType::Teacher], true) || Employee::query()->where('user_id', $user->id)->exists()) {
            $ctx['staff'] = true;
            $ctx['teacher'] = $type === UserType::Teacher || Employee::query()->where('user_id', $user->id)->where('employee_type', 'teacher')->exists();
        }

        $students = collect();
        if ($type === UserType::Student && ($student = Student::query()->where('user_id', $user->id)->first())) {
            $ctx['student'] = true;
            $students->push($student);
        }
        if ($type === UserType::Parent && ($guardian = Guardian::query()->where('user_id', $user->id)->first())) {
            $ctx['guardian'] = true;
            $students = $students->merge($guardian->students()->get());
        }
        foreach ($students as $student) {
            $enrollment = $student->currentEnrollment;
            $ctx['student_ids'][] = $student->id;
            if ($enrollment) {
                $ctx['class_ids'][] = (int) $enrollment->school_class_id;
                $ctx['section_ids'][] = (int) $enrollment->section_id;
            }
        }

        return $ctx;
    }
}
