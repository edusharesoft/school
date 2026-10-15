<?php

namespace App\Services\Reports\Definitions\Concerns;

use App\Models\Academic\SchoolClass;
use App\Models\HR\Employee;
use App\Models\Students\Student;
use Illuminate\Support\Collection;

/** Small helpers shared by report definitions (member names, class lists). */
trait Lookups
{
    /**
     * Names of the students / employees behind rows that carry member_type + member_id.
     *
     * @param  iterable<object>  $rows
     * @return array<string,array{name:string,code:?string,type:string}>  keyed "student:12"
     */
    protected function members(iterable $rows, string $typeField = 'member_type', string $idField = 'member_id'): array
    {
        $rows = collect($rows);
        $students = Student::query()->whereIn('id', $rows->where($typeField, 'student')->pluck($idField)->unique())->get(['id', 'name', 'student_code'])->keyBy('id');
        $employees = Employee::query()->whereIn('id', $rows->where($typeField, 'employee')->pluck($idField)->unique())->get(['id', 'name', 'employee_code'])->keyBy('id');
        $map = [];
        foreach ($students as $s) {
            $map['student:'.$s->id] = ['name' => $s->name, 'code' => $s->student_code, 'type' => __('Student')];
        }
        foreach ($employees as $e) {
            $map['employee:'.$e->id] = ['name' => $e->name, 'code' => $e->employee_code, 'type' => __('Staff')];
        }

        return $map;
    }

    /** @return array<int,string> class id => name, in class order */
    protected function classOptions(): array
    {
        return SchoolClass::query()->active()->ordered()->pluck('name', 'id')->all();
    }

    protected function bn(?string $en, ?string $bn): string
    {
        return app()->getLocale() === 'bn' && $bn ? $bn : (string) $en;
    }

    protected function yesNo(bool $value): string
    {
        return $value ? (string) __('Yes') : (string) __('No');
    }

    /** @param Collection<int,mixed>|array<int,mixed> $items */
    protected function labels(array $map, ?string $key): string
    {
        return $key === null ? '' : (string) __($map[$key] ?? ucfirst(str_replace('_', ' ', $key)));
    }
}
