<?php

namespace App\Services\Library;

use App\Models\HR\Employee;
use App\Models\Students\Student;
use Illuminate\Support\Collection;

/** Library members are the branch's students and employees (a "library account" is just their loans, fines and reservations). */
class LibraryMembers
{
    public static function find(string $type, int $id): Student|Employee|null
    {
        return match ($type) {
            'student' => Student::query()->with('currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name')->find($id),
            'employee' => Employee::query()->with('designation:id,name,name_bn')->find($id),
            default => null,
        };
    }

    /** Resolve "student:12" / "employee:3" (the value of the member pickers). */
    public static function parse(?string $key): ?array
    {
        if (! $key || ! preg_match('/^(student|employee):(\d+)$/', $key, $m)) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }

    public static function key(string $type, int $id): string
    {
        return $type.':'.$id;
    }

    /** Active members matching a name / code / phone, students first. @return Collection<int,array{type:string,id:int,name:string,code:?string,detail:?string}> */
    public static function search(string $term, int $limit = 20): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }
        $students = Student::query()->search($term)->where('students.status', 'active')->with('currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name')->orderBy('name')->limit($limit)->get()
            ->map(fn (Student $s) => ['type' => 'student', 'id' => $s->id, 'name' => $s->name, 'code' => $s->student_code,
                'detail' => trim(($s->currentEnrollment?->schoolClass?->name ?? '').' '.($s->currentEnrollment?->section?->name ?? '')) ?: null]);
        $employees = Employee::query()->search($term)->where('employees.status', 'active')->with('designation:id,name,name_bn')->orderBy('name')->limit($limit)->get()
            ->map(fn (Employee $e) => ['type' => 'employee', 'id' => $e->id, 'name' => $e->name, 'code' => $e->employee_code, 'detail' => $e->designation?->displayName()]);

        return $students->concat($employees)->take($limit)->values();
    }

    public static function isActive(Student|Employee $member): bool
    {
        return $member->status === 'active';
    }

    public static function type(Student|Employee $member): string
    {
        return $member instanceof Employee ? 'employee' : 'student';
    }

    public static function name(Student|Employee|null $member): string
    {
        return $member?->name ?? '—';
    }

    public static function code(Student|Employee|null $member): ?string
    {
        return $member instanceof Employee ? $member->employee_code : $member?->student_code;
    }

    /** Load the member of every row with two queries (rows need member_type + member_id). */
    public static function attach(iterable $rows): void
    {
        $rows = $rows instanceof \Illuminate\Pagination\AbstractPaginator ? $rows->getCollection() : collect($rows);
        $students = Student::query()->whereIn('id', $rows->where('member_type', 'student')->pluck('member_id')->unique())->get()->keyBy('id');
        $employees = Employee::query()->whereIn('id', $rows->where('member_type', 'employee')->pluck('member_id')->unique())->get()->keyBy('id');
        foreach ($rows as $row) {
            $row->setRelation('memberModel', $row->member_type === 'employee' ? $employees->get($row->member_id) : $students->get($row->member_id));
        }
    }
}
