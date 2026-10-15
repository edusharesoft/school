<?php

namespace App\Services\Reports\Definitions\People;

use App\Models\Department;
use App\Models\HR\Employee;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** Staff directory. Salary, NID and other restricted fields are never part of it. */
class StaffListReport extends Report
{
    use Lookups;

    public function key(): string { return 'hr.staff-list'; }

    public function title(): string { return __('Staff list'); }

    public function description(): string { return __('Teachers and staff with department, designation, contact and joining date.'); }

    public function permission(): string { return 'employees.view'; }

    public function filters(): array
    {
        return [['name' => 'type', 'type' => 'select', 'label' => 'Type', 'options' => ['teacher' => __('Teachers'), 'staff' => __('Staff')]],
            ['name' => 'department', 'type' => 'select', 'label' => 'Department', 'options' => fn () => Department::query()->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'default' => 'active', 'options' => ['active' => __('Active'), 'all' => __('All'), 'resigned' => __('Resigned'), 'terminated' => __('Terminated'), 'retired' => __('Retired'), 'suspended' => __('Suspended')]],
            ['name' => 'q', 'type' => 'text', 'label' => 'Search']];
    }

    public function run(array $filters): R
    {
        $status = $filters['status'] ?? 'active';
        $rows = Employee::query()->with(['department:id,name', 'designation:id,name,name_bn'])->search($filters['q'] ?? null)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('employee_type', $v))
            ->when($filters['department'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('employee_type')->orderBy('name')->get()
            ->map(fn (Employee $e) => ['code' => $e->employee_code, 'name' => $e->name, 'type' => __(ucfirst($e->employee_type)), 'department' => $e->department?->name, 'designation' => $e->designation?->displayName(),
                'employment' => __(ucfirst(str_replace('_', ' ', $e->employment_type))), 'phone' => $e->phone, 'email' => $e->email, 'gender' => __(ucfirst($e->gender)), 'joined' => $e->joining_date,
                'experience' => (float) $e->experience_years, 'status' => __(ucfirst($e->status))])->all();

        return $this->result([R::col('code', __('Employee ID')), R::col('name', __('Name')), R::col('type', __('Type')), R::col('department', __('Department')), R::col('designation', __('Designation')),
            R::col('employment', __('Employment')), R::col('phone', __('Phone')), R::col('email', __('Email')), R::col('gender', __('Gender')), R::col('joined', __('Joined'), 'date'),
            R::col('experience', __('Experience (years)'), 'number'), R::col('status', __('Status'))], $rows);
    }
}
