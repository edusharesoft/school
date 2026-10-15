<?php

namespace App\Services\Notifications;

use App\Models\Academic\AcademicSession;
use App\Models\HR\Employee;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

/** Turns students, audiences and users into Recipients (with contact details and preferred language). */
class RecipientResolver
{
    /** @param iterable<Student>|Student $students @return Collection<string,Recipient> keyed by recipient key */
    public function guardiansOf(iterable|Student $students, array $vars = []): Collection
    {
        $students = $students instanceof Student ? collect([$students]) : collect($students);
        $students->each(fn (Student $s) => $s->loadMissing('guardians.user:id,locale'));

        $out = collect();
        foreach ($students as $student) {
            foreach ($student->guardians as $guardian) {
                $recipient = $this->guardian($guardian)->with(['student_name' => $student->name] + $vars);
                // One message per guardian per student (a guardian of two children gets both).
                $out->put($recipient->key().'#'.$student->id, $recipient);
            }
        }

        return $out;
    }

    public function guardian(Guardian $guardian): Recipient
    {
        return new Recipient('guardian', $guardian->id, $guardian->name, $guardian->phone, $guardian->email, $guardian->user_id, $guardian->user?->locale);
    }

    public function student(Student $student, array $vars = []): Recipient
    {
        $student->loadMissing('user:id,locale');

        return new Recipient('student', $student->id, $student->name, $student->phone, $student->email, $student->user_id, $student->user?->locale, ['student_name' => $student->name] + $vars);
    }

    public function employee(Employee $employee): Recipient
    {
        return new Recipient('employee', $employee->id, $employee->name, $employee->phone, $employee->email, $employee->user_id, $employee->user?->locale);
    }

    public function user(User $user): Recipient
    {
        return new Recipient('user', $user->id, $user->name, $user->phone, $user->email, $user->id, $user->locale);
    }

    /**
     * Audience of a notice or bulk message.
     *  all | staff | teachers | students | guardians | classes (+class_ids / section_ids) | students_specific (+student_ids)
     *
     * @return Collection<string,Recipient>
     */
    public function audience(string $type, array $filter = []): Collection
    {
        $out = collect();
        $students = fn () => $this->activeStudents($type, $filter);

        if (in_array($type, ['all', 'staff', 'teachers'], true)) {
            $employees = Employee::query()->active()->with('user:id,locale')->when($type === 'teachers', fn ($q) => $q->teachers())->get();
            foreach ($employees as $employee) {
                $r = $this->employee($employee);
                $out->put($r->key(), $r);
            }
        }
        if (in_array($type, ['all', 'students', 'classes', 'students_specific'], true)) {
            foreach ($students() as $student) {
                $r = $this->student($student);
                $out->put($r->key(), $r);
            }
        }
        if (in_array($type, ['all', 'guardians', 'classes', 'students_specific'], true)) {
            foreach ($students()->load('guardians.user:id,locale') as $student) {
                foreach ($student->guardians as $guardian) {
                    $r = $this->guardian($guardian);
                    $out->put($r->key(), $r);   // one message per guardian for audience messages
                }
            }
        }

        return $out;
    }

    /** Active students of the current session, optionally narrowed by class / section / explicit ids. */
    public function activeStudents(string $type = 'students', array $filter = []): Collection
    {
        $session = AcademicSession::query()->current()->value('id');
        $classIds = array_map('intval', (array) ($filter['class_ids'] ?? []));
        $sectionIds = array_map('intval', (array) ($filter['section_ids'] ?? []));
        $studentIds = array_map('intval', (array) ($filter['student_ids'] ?? []));

        if ($type === 'students_specific') {
            return Student::query()->active()->whereIn('id', $studentIds ?: [0])->with('user:id,locale')->get();
        }

        $enrolled = StudentEnrollment::query()->where('academic_session_id', $session ?? 0)->active()
            ->when($type === 'classes', fn ($q) => $q->where(fn ($w) => $w->whereIn('school_class_id', $classIds ?: [0])->orWhereIn('section_id', $sectionIds ?: [0])))
            ->select('student_id');

        return Student::query()->active()->whereIn('id', $enrolled)->with('user:id,locale')->get();
    }
}
