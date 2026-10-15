<?php

namespace App\Services\Reports\Definitions\People;

use App\Models\Academic\Section;
use App\Models\Students\Student;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Student directory of the branch for the current session. */
class StudentListReport extends Report
{
    use Lookups;

    public function key(): string { return 'students.list'; }

    public function title(): string { return __('Student list'); }

    public function description(): string { return __('Students with class, roll, contact and primary guardian; filter by class, section and status.'); }

    public function permission(): string { return 'students.view'; }

    public function exportPermission(): string { return 'students.export'; }

    public function filters(): array
    {
        return [['name' => 'class_id', 'type' => 'select', 'label' => 'Class', 'options' => fn () => $this->classOptions()],
            ['name' => 'section_id', 'type' => 'select', 'label' => 'Section', 'options' => fn () => Section::query()->with('schoolClass:id,name,numeric_order')->get()
                ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->mapWithKeys(fn ($s) => [$s->id => trim(($s->schoolClass?->name ?? '').' — '.$s->name)])->all()],
            ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'default' => 'active', 'options' => ['active' => __('Active'), 'all' => __('All'), 'transferred' => __('Transferred'), 'withdrawn' => __('Withdrawn'), 'graduated' => __('Graduated'), 'suspended' => __('Suspended')]],
            ['name' => 'gender', 'type' => 'select', 'label' => 'Gender', 'options' => ['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')]],
            ['name' => 'q', 'type' => 'text', 'label' => 'Search']];
    }

    public function run(array $filters): R
    {
        $status = $filters['status'] ?? 'active';
        $students = Student::query()->with(['currentEnrollment.schoolClass:id,name,numeric_order', 'currentEnrollment.section:id,name', 'guardians:id,name,phone'])
            ->search($filters['q'] ?? null)
            ->when($status !== 'all', fn ($q) => $q->where('students.status', $status))
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($filters['class_id'] ?? null, fn ($q, $v) => $q->whereHas('currentEnrollment', fn ($e) => $e->where('school_class_id', $v)))
            ->when($filters['section_id'] ?? null, fn ($q, $v) => $q->whereHas('currentEnrollment', fn ($e) => $e->where('section_id', $v)))
            ->get()
            ->sortBy(fn (Student $s) => sprintf('%05d-%s-%06d-%s', $s->currentEnrollment?->schoolClass?->numeric_order ?? 99999, $s->currentEnrollment?->section?->name, $s->currentEnrollment?->roll_no ?? 0, $s->name))->values();

        $rows = $students->map(function (Student $s) {
            $g = $s->guardians->firstWhere('pivot.is_primary', true) ?? $s->guardians->first();

            return ['code' => $s->student_code, 'name' => $s->name, 'class' => $s->currentEnrollment?->schoolClass?->name, 'section' => $s->currentEnrollment?->section?->name, 'roll' => $s->currentEnrollment?->roll_no,
                'gender' => __(ucfirst($s->gender)), 'dob' => $s->date_of_birth, 'blood' => $s->blood_group, 'phone' => $s->phone, 'guardian' => $g?->name, 'guardian_phone' => $g?->phone,
                'admitted' => $s->admission_date, 'status' => __(ucfirst($s->status))];
        })->all();

        return $this->result([R::col('code', __('Student ID')), R::col('name', __('Name')), R::col('class', __('Class')), R::col('section', __('Section')), R::col('roll', __('Roll'), 'int'),
            R::col('gender', __('Gender')), R::col('dob', __('Date of birth'), 'date'), R::col('blood', __('Blood group')), R::col('phone', __('Phone')), R::col('guardian', __('Guardian')),
            R::col('guardian_phone', __('Guardian phone')), R::col('admitted', __('Admitted'), 'date'), R::col('status', __('Status'))], $rows);
    }
}
