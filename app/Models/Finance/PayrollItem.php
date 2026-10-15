<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToBranch;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use BelongsToBranch;

    protected $fillable = ['payroll_run_id', 'employee_id', 'basic', 'allowances', 'gross', 'deductions', 'unpaid_days', 'unpaid_deduction', 'total_deductions', 'net', 'remarks'];

    protected function casts(): array
    {
        return ['basic' => 'float', 'allowances' => 'array', 'gross' => 'float', 'deductions' => 'array', 'unpaid_days' => 'integer', 'unpaid_deduction' => 'float',
            'total_deductions' => 'float', 'net' => 'float', 'payroll_run_id' => 'integer', 'employee_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
